<?php

namespace App\Services;

use App\Models\DeliveryFeeRule;
use RuntimeException;

class DeliveryFeeService
{
    public function calculate(int $productId, ?int $variantId, int|float $qty): float
    {
        $rule = DeliveryFeeRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($productId, $variantId) {
                if ($variantId) {
                    $q->where(function ($qq) use ($productId, $variantId) {
                        $qq->where('product_id', $productId)
                            ->where('variant_id', $variantId);
                    })->orWhere(function ($qq) use ($productId) {
                        $qq->where('product_id', $productId)
                            ->whereNull('variant_id');
                    });
                } else {
                    $q->where('product_id', $productId)
                        ->whereNull('variant_id');
                }
            })
            ->orderByRaw('CASE WHEN variant_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('priority')
            ->first();

        if (! $rule) {
            return 0;
        }

        if ($rule->fixed_fee !== null) {
            return (float) $rule->fixed_fee;
        }

        if (! $rule->formula_fee) {
            return 0;
        }

        return $this->evaluateFormula($rule->formula_fee, [
            'qty' => $qty,
        ]);
    }

    protected function evaluateFormula(string $formula, array $context): float
    {
        $qty = (float) ($context['qty'] ?? 0);

        $normalized = preg_replace('/\s+/', '', strtolower($formula));

        // allow only qty, floor, numbers, spaces, math/comparison/ternary chars
        if (preg_match('/[^0-9qtyfloor\+\-\*\/\(\)\?\:\<\>\=\!\.\s]/', $normalized)) {
            throw new RuntimeException('Unsafe formula detected.');
        }

        $expr = str_ireplace('qty', '$qty', $formula);

        try {
            /** @var float|int $result */
            $result = (function () use ($expr, $qty) {
                return eval('return ' . $expr . ';');
            })();
        } catch (\Throwable $e) {
            throw new RuntimeException('Formula evaluation failed: ' . $e->getMessage(), 0, $e);
        }

        return (float) $result;
    }
}
