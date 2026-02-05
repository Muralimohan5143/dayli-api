<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

// If your project already uses PhpSpreadsheet in ImportSheetSubscriptions.php,
// reuse the same style. This import is optional (payments only).
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMonthlyBillingAndPayments extends Command
{
    protected $signature = 'billing:import
        {--month= : YYYY-MM (example 2025-12)}
        {--xlsx= : Optional Excel file path (only needed if you want to import payments)}
        {--sheet= : Excel sheet name (optional). Default: first sheet}
        {--dry-run : Do not write to DB}
        {--payments : Import inward_payments from excel if columns exist}
    ';

    protected $description = 'Generate invoices from order_items.actuals_date for a month and optionally import inward_payments from Excel';

    public function handle(): int
    {
        $month = trim((string) $this->option('month'));
        if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Invalid --month. Use YYYY-MM like 2025-12");
            return 1;
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->addMonth()->startOfMonth()->toDateString();
        $invoiceDate = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $dryRun = (bool) $this->option('dry-run');

        $this->info("Month window: [$monthStart .. $monthEnd) invoice_date=$invoiceDate");
        $this->info($dryRun ? "DRY-RUN enabled (no DB writes)" : "DB writes enabled");

        // ---------------------------
        // A) Generate / Upsert invoices
        // ---------------------------
        $createdOrUpdated = $this->generateInvoices($monthStart, $monthEnd, $invoiceDate, $dryRun);
        $this->info("Invoices processed: {$createdOrUpdated}");

        // ---------------------------
        // B) Optional: Import inward_payments from Excel
        // ---------------------------
        if ($this->option('payments')) {
            $xlsx = (string) $this->option('xlsx');
            if (!$xlsx) {
                $this->warn("You passed --payments but no --xlsx. Skipping payments import.");
            } else {
                $imported = $this->importPaymentsFromExcel($xlsx, (string)$this->option('sheet'), $monthStart, $monthEnd, $dryRun);
                $this->info("Payments imported/upserted: {$imported}");
            }
        }

        return 0;
    }

    /**
     * Delivery fee formula (ported from sheet):
     * =if(LT(quotient(delivery_count, 25), 1),
     *      delivery_count * IF(arokya,1, IF(small,1,2)),
     *      IF(OR(arokya,small), delivery_count, quotient(delivery_count, 25) * 50)
     * )
     */
    private function computeDeliveryFee(int $deliveryCount, string $milkType): int
    {
        $t = Str::of($milkType)->lower();
        $isArokya = $t->contains('arokya');
        $isSmall = $t->contains('small');

        $q = intdiv(max($deliveryCount, 0), 25); // quotient

        if ($q < 1) {
            $rate = ($isArokya || $isSmall) ? 1 : 2;
            return $deliveryCount * $rate;
        }

        if ($isArokya || $isSmall) {
            return $deliveryCount;
        }

        return $q * 50;
    }

    private function generateInvoices(string $monthStart, string $monthEnd, string $invoiceDate, bool $dryRun): int
    {
        // Get deliveries grouped by customer + title for the month
        // We join orders for customer_id.
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNotNull('oi.actuals_date')
            ->where('oi.actuals_date', '>=', $monthStart)
            ->where('oi.actuals_date', '<', $monthEnd)
            ->selectRaw('
              o.customer_id as user_id,
                MIN(o.id) as any_order_id,
                MIN(o.order_type) as any_order_type,
                oi.title as title,
                SUM(oi.quantity) as monthly_count,
                AVG(oi.unit_price) as unit_price_avg,
                SUM(oi.line_total) as line_total_sum
            ')
            ->groupBy('o.customer_id', 'oi.title')
            ->orderBy('o.customer_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn("No order_items found in month window. (Check actuals_date)");
            return 0;
        }

        // Group in PHP by customer_id
        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int)$r->user_id;

            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id' => $uid,
                    'order_id' => (int)$r->any_order_id,
                    'order_type' => (string)$r->any_order_type,
                    'items' => [],
                ];
            }

            $byUser[$uid]['items'][] = $r;
        }


        $processed = 0;

        foreach ($byUser as $uid => $payload) {

            // 1) Subtotal = sum(line_total_sum) across all titles
            $subtotal = 0.0;
            $deliveryFeeTotal = 0;

            foreach ($payload['items'] as $it) {
                $subtotal += (float)$it->line_total_sum;

                // delivery_count: best available is monthly_count (like sheet)
                $deliveryCount = (int) round((float)$it->monthly_count);
                $deliveryFeeTotal += $this->computeDeliveryFee($deliveryCount, (string)$it->title);
            }

            // 2) Previous dues = (historical invoices grand_total) - (historical payments amount)
            $previousDues = $this->computePreviousDues($uid, $monthStart);

            // 3) Totals
            $grandTotal = round($subtotal + $deliveryFeeTotal + $previousDues, 2);

            // 4) Determine billing name/phone from users
            $u = DB::table('users')->where('id', $uid)->select('display_name', 'name', 'first_name', 'last_name', 'phone')->first();
            $billingName = $this->pickName($u);
            $billingPhone = $u?->phone;

            // 5) Invoice number (idempotent key)
            // Use deterministic number to prevent duplicates on re-run
            $invNumber = "MILK-" . str_replace('-', '', substr($monthStart, 0, 7)) . "-" . $uid; // MILK-202512-10910

            $data = [
                'order_id' => $payload['order_id'],
                'order_type' => $payload['order_type'],
                'order_start_date' => $monthStart,
                'order_end_date' => Carbon::parse($monthEnd)->subDay()->toDateString(),
                'user_id' => $uid,
                'billing_name' => $billingName ?: 'Customer',
                'invoice_date' => $invoiceDate,
                'status' => 'issued',
                'payment_status' => 'unpaid',
                'gst_status' => 'unfiled',
                'subtotal' => round($subtotal, 2),
                'Unpaid_dues' => round($previousDues, 2),
                'tax' => 0,
                'tax_total' => 0,
                'discount' => 0,
                'delivery_fee' => round($deliveryFeeTotal, 2),
                'total' => round($subtotal + $deliveryFeeTotal, 2),
                'grand_total' => $grandTotal,
                'currency' => 'INR',
                'number' => $invNumber,
                'invoice_number' => $invNumber,
                'meta' => json_encode([
                    'month' => substr($monthStart, 0, 7),
                    'billing_phone' => $billingPhone,
                ]),
                'updated_at' => now(),
            ];

            if ($dryRun) {
                $this->line("DRY-RUN invoice: user_id={$uid} number={$invNumber} grand_total={$grandTotal}");

                $processed++;
                continue;
            }

            DB::table('invoices')->updateOrInsert(
                ['number' => $invNumber],
                array_merge($data, ['created_at' => DB::raw('COALESCE(created_at, NOW())')])
            );

            $processed++;
        }

        return $processed;
    }

    private function computePreviousDues(int $userId, string $monthStart): float
    {
        // sum invoices before monthStart
        $invSum = (float) DB::table('invoices')
            ->where('user_id', $userId)
            ->whereNotNull('invoice_date')
            ->where('invoice_date', '<', $monthStart)
            ->sum('grand_total');

        // sum payments before monthStart (linked to invoices or by payment_date)
        $paySum = (float) DB::table('inward_payments as p')
            ->leftJoin('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where(function ($q) use ($userId) {
                $q->where('i.user_id', $userId);
            })
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('p.payment_date')
                    ->orWhere('p.payment_date', '<', $monthStart);
            })
            ->sum('p.amount');

        return max(0, round($invSum - $paySum, 2));
    }

    private function pickName($u): string
    {
        if (!$u) return '';
        if (!empty($u->display_name)) return (string)$u->display_name;
        if (!empty($u->name)) return (string)$u->name;
        $fn = trim((string)($u->first_name ?? ''));
        $ln = trim((string)($u->last_name ?? ''));
        return trim($fn . ' ' . $ln);
    }

    private function importPaymentsFromExcel(string $xlsxPath, string $sheetName, string $monthStart, string $monthEnd, bool $dryRun): int
    {
        if (!file_exists($xlsxPath)) {
            $this->error("Excel not found: {$xlsxPath}");
            return 0;
        }

        $spreadsheet = IOFactory::load($xlsxPath);

        $sheet = null;
        if ($sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
        }
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 2) {
            $this->warn("Excel has no rows.");
            return 0;
        }

        // Header row = first row
        $header = array_map(fn($v) => Str::of((string)$v)->lower()->trim()->__toString(), $rows[1]);

        // We detect columns flexibly (you can rename later)
        $colPhone  = $this->findCol($header, ['phone', 'mobile', 'contact']);
        $colAmount = $this->findCol($header, ['paid amount', 'amount', 'payment', 'paid']);
        $colDate   = $this->findCol($header, ['paid date', 'payment date', 'date']);
        $colMethod = $this->findCol($header, ['method', 'mode', 'payment mode']);
        $colNote   = $this->findCol($header, ['note', 'remarks', 'txn', 'transaction']);

        if (!$colPhone || !$colAmount) {
            $this->warn("Could not detect payment columns. Need at least Phone + Amount in header.");
            $this->warn("Detected phone={$colPhone}, amount={$colAmount}, date={$colDate}, method={$colMethod}, note={$colNote}");
            return 0;
        }

        $imported = 0;

        for ($i = 2; $i <= count($rows); $i++) {
            $r = $rows[$i] ?? null;
            if (!$r) continue;

            $phone = trim((string)($r[$colPhone] ?? ''));
            $amountRaw = trim((string)($r[$colAmount] ?? ''));
            if ($phone === '' || $amountRaw === '') continue;

            $amount = (float) preg_replace('/[^0-9.]/', '', $amountRaw);
            if ($amount <= 0) continue;

            // payment_date (optional) - if empty set to monthEnd-1
            $dateStr = $colDate ? trim((string)($r[$colDate] ?? '')) : '';
            $payDate = $dateStr ? $this->parseExcelDate($dateStr) : Carbon::parse($monthEnd)->subDay()->toDateString();

            // Keep payments inside the month window only
            if ($payDate < $monthStart || $payDate >= $monthEnd) {
                continue;
            }

            $method = $colMethod ? trim((string)($r[$colMethod] ?? '')) : null;
            $note = $colNote ? trim((string)($r[$colNote] ?? '')) : null;

            // Find user by phone (normalize digits)
            $norm = preg_replace('/\D+/', '', $phone);
            $user = DB::table('users')
                ->whereRaw("REGEXP_REPLACE(phone, '[^0-9]', '') = ?", [$norm])
                ->select('id')
                ->first();

            if (!$user) continue;

            $customerId = (int)$user->id;

            // Find invoice for this month
            $invNumber = "MILK-" . str_replace('-', '', substr($monthStart, 0, 7)) . "-" . $customerId;

            $invoice = DB::table('invoices')->where('number', $invNumber)->select('id', 'grand_total')->first();
            if (!$invoice) continue;

            // Idempotency key for payment row
            $uniqKey = sha1($invNumber . '|' . $payDate . '|' . $amount . '|' . ($method ?? '') . '|' . ($note ?? ''));

            if ($dryRun) {
                $this->line("DRY-RUN payment: invoice={$invNumber} date={$payDate} amount={$amount}");
                $imported++;
                continue;
            }

            // If same payment already imported, skip
            $exists = DB::table('inward_payments')->where('note', 'like', '%uniq:' . $uniqKey . '%')->exists();
            if ($exists) continue;

            // due_amount = invoice grand_total - total payments (including this one after insert)
            DB::table('inward_payments')->insert([
                'order_id' => null,
                'invoice_id' => (int)$invoice->id,
                'previous_payment_id' => null,
                'payment_date' => $payDate,
                'amount' => round($amount, 2),
                'due_amount' => 0, // update below
                'currency' => 'INR',
                'method' => $method ?: null,
                'note' => trim(($note ?: '') . ' uniq:' . $uniqKey),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $paidSum = (float) DB::table('inward_payments')->where('invoice_id', $invoice->id)->sum('amount');
            $due = max(0, round((float)$invoice->grand_total - $paidSum, 2));

            // update latest inserted row due_amount
            DB::table('inward_payments')
                ->where('invoice_id', $invoice->id)
                ->where('note', 'like', '%uniq:' . $uniqKey . '%')
                ->update(['due_amount' => $due, 'updated_at' => now()]);

            $imported++;
        }

        return $imported;
    }

    private function findCol(array $header, array $needles): ?string
    {
        foreach ($header as $col => $name) {
            foreach ($needles as $n) {
                if ($name === Str::of($n)->lower()->trim()->__toString()) return $col;
                if (Str::of($name)->contains(Str::of($n)->lower())) return $col;
            }
        }
        return null;
    }

    private function parseExcelDate(string $v): string
    {
        // Accept "YYYY-MM-DD", "DD/MM/YYYY", "MM/DD/YYYY", etc.
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            // fallback: return today
            return Carbon::today()->toDateString();
        }
    }
}
