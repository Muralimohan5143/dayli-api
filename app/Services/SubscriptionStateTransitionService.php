<?php

namespace App\Services;

use App\Models\DraftOrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SubscriptionStateTransitionService
{
    protected array $allowedFrequencies = [
        'daily',
        'alternate_days',
        'weekdays',
        'weekends',
        'sat',
        'sun',
        'custom',
        'on_demand',
    ];
    public function changeActiveToActive(
        int $currentDoiId,
        string $changeStartDate,
        ?string $changeEndDate,
        float $newQty,
        string $newFrequencyType
    ): array {
        $this->validateQty($newQty);
        $this->validateFrequency($newFrequencyType);

        $changeStart = Carbon::parse($changeStartDate)->startOfDay();
        $changeEnd = $changeEndDate ? Carbon::parse($changeEndDate)->startOfDay() : null;

        if ($changeEnd && $changeEnd->lt($changeStart)) {
            throw new InvalidArgumentException('change_end_date must be >= change_start_date');
        }

        return DB::transaction(function () use (
            $currentDoiId,
            $changeStart,
            $changeEnd,
            $newQty,
            $newFrequencyType
        ) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_ACTIVE);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $changeStart);

            $this->closeDoi(
                $current,
                $changeStart->copy()->subDay(),
                DraftOrderItem::ACTION_MODIFY
            );

            $changed = $this->createDoiFromBase($current, [
                'start_date' => $changeStart->toDateString(),
                'end_date' => $changeEnd?->toDateString(),
                'qty' => $newQty,
                'frequency_type' => $newFrequencyType,
                'status' => DraftOrderItem::STATUS_ACTIVE,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_MODIFY,
                'closed_by_action' => null,
            ]);

            $restored = null;

            if ($changeEnd) {
                $restored = $this->createDoiFromBase($current, [
                    'start_date' => $changeEnd->copy()->addDay()->toDateString(),
                    'end_date' => null,
                    'qty' => $current->qty,
                    'frequency_type' => $current->frequency_type,
                    'status' => DraftOrderItem::STATUS_ACTIVE,
                    'supersedes_doi_id' => $changed->id,
                    'created_from_action' => DraftOrderItem::ACTION_SYSTEM,
                    'closed_by_action' => null,
                ]);
            }

            return [
                'closed_current' => $current->fresh(),
                'changed_doi' => $changed,
                'restored_doi' => $restored,
            ];
        });
    }

    public function pauseActive(
        int $currentDoiId,
        string $pauseStartDate,
        ?string $pauseEndDate = null
    ): array {
        $pauseStart = Carbon::parse($pauseStartDate)->startOfDay();
        $pauseEnd = $pauseEndDate ? Carbon::parse($pauseEndDate)->startOfDay() : null;

        if ($pauseEnd && $pauseEnd->lt($pauseStart)) {
            throw new InvalidArgumentException('pause_end_date must be >= pause_start_date');
        }

        return DB::transaction(function () use ($currentDoiId, $pauseStart, $pauseEnd) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_ACTIVE);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $pauseStart);

            $this->closeDoi(
                $current,
                $pauseStart->copy()->subDay(),
                DraftOrderItem::ACTION_PAUSE
            );

            $paused = $this->createDoiFromBase($current, [
                'start_date' => $pauseStart->toDateString(),
                'end_date' => $pauseEnd?->toDateString(),
                'qty' => 0,
                'frequency_type' => null,
                'status' => DraftOrderItem::STATUS_PAUSED,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_PAUSE,
                'closed_by_action' => null,
            ]);

            $resumed = null;

            if ($pauseEnd) {
                $resumed = $this->createDoiFromBase($current, [
                    'start_date' => $pauseEnd->copy()->addDay()->toDateString(),
                    'end_date' => null,
                    'qty' => $current->qty,
                    'frequency_type' => $current->frequency_type,
                    'status' => DraftOrderItem::STATUS_ACTIVE,
                    'supersedes_doi_id' => $paused->id,
                    'created_from_action' => DraftOrderItem::ACTION_SYSTEM,
                    'closed_by_action' => null,
                ]);
            }

            return [
                'closed_current' => $current->fresh(),
                'paused_doi' => $paused,
                'resumed_doi' => $resumed,
            ];
        });
    }

    public function cancelActive(
        int $currentDoiId,
        string $cancelStartDate
    ): array {
        $cancelStart = Carbon::parse($cancelStartDate)->startOfDay();

        return DB::transaction(function () use ($currentDoiId, $cancelStart) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_ACTIVE);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $cancelStart);

            $this->closeDoi(
                $current,
                $cancelStart->copy()->subDay(),
                DraftOrderItem::ACTION_CANCEL
            );

            $cancelled = $this->createDoiFromBase($current, [
                'start_date' => $cancelStart->toDateString(),
                'end_date' => null,
                'qty' => 0,
                'frequency_type' => null,
                'status' => DraftOrderItem::STATUS_CANCELLED,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_CANCEL,
                'closed_by_action' => null,
            ]);

            return [
                'closed_current' => $current->fresh(),
                'cancelled_doi' => $cancelled,
            ];
        });
    }

    public function resumePaused(
        int $currentDoiId,
        string $resumeStartDate,
        float $qty,
        string $frequencyType,
        ?string $resumeEndDate = null
    ): array {
        $this->validateQty($qty);
        $this->validateFrequency($frequencyType);

        $resumeStart = Carbon::parse($resumeStartDate)->startOfDay();
        $resumeEnd = $resumeEndDate ? Carbon::parse($resumeEndDate)->startOfDay() : null;

        if ($resumeEnd && $resumeEnd->lt($resumeStart)) {
            throw new InvalidArgumentException('resume_end_date must be >= resume_start_date');
        }

        return DB::transaction(function () use (
            $currentDoiId,
            $resumeStart,
            $resumeEnd,
            $qty,
            $frequencyType
        ) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_PAUSED);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $resumeStart);

            $this->closeDoi(
                $current,
                $resumeStart->copy()->subDay(),
                DraftOrderItem::ACTION_RESUME
            );

            $active = $this->createDoiFromBase($current, [
                'start_date' => $resumeStart->toDateString(),
                'end_date' => $resumeEnd?->toDateString(),
                'qty' => $qty,
                'frequency_type' => $frequencyType,
                'status' => DraftOrderItem::STATUS_ACTIVE,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_RESUME,
                'closed_by_action' => null,
            ]);

            return [
                'closed_current' => $current->fresh(),
                'active_doi' => $active,
            ];
        });
    }

    public function extendPause(
        int $currentDoiId,
        string $newPauseStartDate,
        ?string $newPauseEndDate = null
    ): array {
        $newPauseStart = Carbon::parse($newPauseStartDate)->startOfDay();
        $newPauseEnd = $newPauseEndDate ? Carbon::parse($newPauseEndDate)->startOfDay() : null;

        if ($newPauseEnd && $newPauseEnd->lt($newPauseStart)) {
            throw new InvalidArgumentException('new_pause_end_date must be >= new_pause_start_date');
        }

        return DB::transaction(function () use ($currentDoiId, $newPauseStart, $newPauseEnd) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_PAUSED);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $newPauseStart);

            $this->closeDoi(
                $current,
                $newPauseStart->copy()->subDay(),
                DraftOrderItem::ACTION_PAUSE
            );

            $paused = $this->createDoiFromBase($current, [
                'start_date' => $newPauseStart->toDateString(),
                'end_date' => $newPauseEnd?->toDateString(),
                'qty' => 0,
                'frequency_type' => null,
                'status' => DraftOrderItem::STATUS_PAUSED,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_PAUSE,
                'closed_by_action' => null,
            ]);

            return [
                'closed_current' => $current->fresh(),
                'paused_doi' => $paused,
            ];
        });
    }

    public function cancelPaused(
        int $currentDoiId,
        string $cancelStartDate
    ): array {
        $cancelStart = Carbon::parse($cancelStartDate)->startOfDay();

        return DB::transaction(function () use ($currentDoiId, $cancelStart) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_PAUSED);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $cancelStart);

            $this->closeDoi(
                $current,
                $cancelStart->copy()->subDay(),
                DraftOrderItem::ACTION_CANCEL
            );

            $cancelled = $this->createDoiFromBase($current, [
                'start_date' => $cancelStart->toDateString(),
                'end_date' => null,
                'qty' => 0,
                'frequency_type' => null,
                'status' => DraftOrderItem::STATUS_CANCELLED,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_CANCEL,
                'closed_by_action' => null,
            ]);

            return [
                'closed_current' => $current->fresh(),
                'cancelled_doi' => $cancelled,
            ];
        });
    }

    public function reactivateCancelled(
        int $currentDoiId,
        string $reactivateStartDate,
        float $qty,
        string $frequencyType,
        ?string $reactivateEndDate = null
    ): array {
        $this->validateQty($qty);
        $this->validateFrequency($frequencyType);

        $reactivateStart = Carbon::parse($reactivateStartDate)->startOfDay();
        $reactivateEnd = $reactivateEndDate ? Carbon::parse($reactivateEndDate)->startOfDay() : null;

        if ($reactivateEnd && $reactivateEnd->lt($reactivateStart)) {
            throw new InvalidArgumentException('reactivate_end_date must be >= reactivate_start_date');
        }

        return DB::transaction(function () use (
            $currentDoiId,
            $reactivateStart,
            $reactivateEnd,
            $qty,
            $frequencyType
        ) {
            $current = DraftOrderItem::query()->lockForUpdate()->findOrFail($currentDoiId);

            $this->assertStatus($current, DraftOrderItem::STATUS_CANCELLED);
            $this->assertNoOtherOpenDoi($current); // ✅ ADD THIS
            $this->assertSplitDateWithinCurrent($current, $reactivateStart);

            $this->closeDoi(
                $current,
                $reactivateStart->copy()->subDay(),
                DraftOrderItem::ACTION_REACTIVATE
            );

            $active = $this->createDoiFromBase($current, [
                'start_date' => $reactivateStart->toDateString(),
                'end_date' => $reactivateEnd?->toDateString(),
                'qty' => $qty,
                'frequency_type' => $frequencyType,
                'status' => DraftOrderItem::STATUS_ACTIVE,
                'supersedes_doi_id' => $current->id,
                'created_from_action' => DraftOrderItem::ACTION_REACTIVATE,
                'closed_by_action' => null,
            ]);

            return [
                'closed_current' => $current->fresh(),
                'active_doi' => $active,
            ];
        });
    }

    protected function assertStatus(DraftOrderItem $doi, string $requiredStatus): void
    {
        if ($doi->status !== $requiredStatus) {
            throw new RuntimeException("DOI {$doi->id} is not {$requiredStatus}.");
        }
    }
    protected function assertNoOtherOpenDoi(DraftOrderItem $current): void
    {
        $exists = DraftOrderItem::query()
            ->where('draft_order_id', $current->draft_order_id)
            ->where('variant_id', $current->variant_id)
            ->where('vendor_id', $current->vendor_id)
            ->whereNull('end_date')
            ->where('id', '!=', $current->id)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Another open DOI exists for this subscription.');
        }
    }

    protected function assertSplitDateWithinCurrent(DraftOrderItem $current, Carbon $newStart): void
    {
        $currentStart = Carbon::parse($current->start_date)->startOfDay();

        if ($newStart->lt($currentStart)) {
            throw new InvalidArgumentException('new start date cannot be before current DOI start date');
        }

        if (!empty($current->end_date)) {
            $currentEnd = Carbon::parse($current->end_date)->startOfDay();

            if ($newStart->gt($currentEnd->copy()->addDay())) {
                throw new InvalidArgumentException('new start date is outside current DOI range');
            }
        }
    }

    protected function validateQty(float $qty): void
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('qty must be > 0 for active DOI');
        }
    }

    protected function validateFrequency(?string $frequency): void
    {
        if (!in_array($frequency, $this->allowedFrequencies, true)) {
            throw new InvalidArgumentException("Invalid frequency_type: {$frequency}");
        }
    }

    protected function closeDoi(
        DraftOrderItem $doi,
        Carbon $closeDate,
        string $closedByAction
    ): DraftOrderItem {
        $start = Carbon::parse($doi->start_date)->startOfDay();

        if ($closeDate->lt($start)) {
            throw new InvalidArgumentException(
                "close date {$closeDate->toDateString()} cannot be before DOI start date {$start->toDateString()}"
            );
        }

        $doi->end_date = $closeDate->toDateString();
        $doi->closed_by_action = $closedByAction;
        $doi->save();

        return $doi;
    }

    protected function createDoiFromBase(DraftOrderItem $base, array $overrides): DraftOrderItem
    {
        $payload = [
            'original_item_id' => $base->original_item_id,
            'change_action' => $base->change_action,
            'draft_order_id' => $base->draft_order_id,
            'product_id' => $base->product_id,
            'variant_id' => $base->variant_id,
            'vendor_id' => $base->vendor_id,
            'frequency_type' => $base->frequency_type,
            'qty' => $base->qty,
            'unit' => $base->unit,
            'price_snapshot' => $base->price_snapshot,
            'start_date' => $base->start_date,
            'end_date' => $base->end_date,
            'status' => $base->status ?: DraftOrderItem::STATUS_ACTIVE,
            'supersedes_doi_id' => null,
            'created_from_action' => null,
            'closed_by_action' => null,
            'meta' => $base->meta, // ✅ ADD THIS
        ];

        return DraftOrderItem::query()->create(array_merge($payload, $overrides));
    }
}
