<?php

namespace Tests\Feature\Services;

use App\Models\DraftOrderItem;
use App\Services\SubscriptionStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use App\Models\SubChangeRequest;
use App\Models\User;
use App\Models\Zone;

class SubscriptionStateTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionStateTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionStateTransitionService::class);
    }

    private function createDraftOrder(): int
    {
        $customerId = DB::table('users')->insertGetId([
            'name' => 'Test Customer',
            'email' => 'customer_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Test Zone',
            'code' => 'Z' . rand(100, 999), // REQUIRED FIELD
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $changeRequestId = DB::table('sub_change_requests')->insertGetId([
            'for_user_id' => $customerId,
            'by_user_id' => $customerId, // REQUIRED FIELD
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('draft_orders')->insertGetId([
            'change_request_id' => $changeRequestId,
            'customer_id' => $customerId,
            'zone_id' => $zoneId,
            'cadence' => 'daily',
            'invoice_cycle' => 'monthly',
            'start_date' => '2026-03-01',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'title' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(): void
    {
        DB::table('products')->insert([
            'product_id' => 8383403720978,
            'title' => 'Test Product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    private function createActiveDoi(
        int $draftOrderId,
        array $overrides = []
    ): DraftOrderItem {

        // ✅ ALWAYS ensure product exists
        DB::table('products')->updateOrInsert(
            ['product_id' => 8383403720978],
            [
                'title' => 'Test Product',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $this->ensureProductAndVariant();

        return DraftOrderItem::query()->create(array_merge([
            'draft_order_id' => $draftOrderId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 8383403720978,
            'variant_id' => 45447791247634,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'pcs',
            'price_snapshot' => 32.00,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => DraftOrderItem::STATUS_ACTIVE,
            'supersedes_doi_id' => null,
            'created_from_action' => null,
            'closed_by_action' => null,
            'meta' => null,
        ], $overrides));
    }
    private function ensureProductAndVariant(): void
    {
        // product
        DB::table('products')->updateOrInsert(
            ['product_id' => 8383403720978],
            [
                'title' => 'Test Product',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // variant
        DB::table('variants')->updateOrInsert(
            ['variant_id' => 45447791247634],
            [
                'product_id' => 8383403720978, // IMPORTANT FK
                'title' => 'Test Variant',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
    private function createPausedDoi(
        int $draftOrderId,
        array $overrides = []
    ): DraftOrderItem {
        $this->ensureProductAndVariant();
        return DraftOrderItem::query()->create(array_merge([
            'draft_order_id' => $draftOrderId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 8383403720978,
            'variant_id' => 45447791247634,
            'vendor_id' => null,
            'frequency_type' => null,
            'qty' => 0.00,
            'unit' => 'pcs',
            'price_snapshot' => 32.00,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => DraftOrderItem::STATUS_PAUSED,
            'supersedes_doi_id' => null,
            'created_from_action' => null,
            'closed_by_action' => null,
            'meta' => null,
        ], $overrides));
    }

    private function createCancelledDoi(
        int $draftOrderId,
        array $overrides = []
    ): DraftOrderItem {
        $this->ensureProductAndVariant();
        return DraftOrderItem::query()->create(array_merge([
            'draft_order_id' => $draftOrderId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 8383403720978,
            'variant_id' => 45447791247634,
            'vendor_id' => null,
            'frequency_type' => null,
            'qty' => 0.00,
            'unit' => 'pcs',
            'price_snapshot' => 32.00,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => DraftOrderItem::STATUS_CANCELLED,
            'supersedes_doi_id' => null,
            'created_from_action' => null,
            'closed_by_action' => null,
            'meta' => null,
        ], $overrides));
    }

    public function test_pause_active_with_end_date_creates_pause_and_resume_rows(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $result = $this->service->pauseActive(
            currentDoiId: $current->id,
            pauseStartDate: '2026-03-10',
            pauseEndDate: '2026-03-15',
        );

        $closed = $result['closed_current']->fresh();
        $paused = $result['paused_doi']->fresh();
        $resumed = $result['resumed_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->start_date->copy()->addDays(8)->toDateString()); // sanity
        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_PAUSE, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_PAUSED, $paused->status);
        $this->assertSame('2026-03-10', $paused->start_date->toDateString());
        $this->assertSame('2026-03-15', $paused->end_date->toDateString());
        $this->assertSame('0.00', (string) $paused->qty);
        $this->assertNull($paused->frequency_type);
        $this->assertSame($current->id, $paused->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_PAUSE, $paused->created_from_action);

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $resumed->status);
        $this->assertSame('2026-03-16', $resumed->start_date->toDateString());
        $this->assertNull($resumed->end_date);
        $this->assertSame('1.00', (string) $resumed->qty);
        $this->assertSame('daily', $resumed->frequency_type);
        $this->assertSame($paused->id, $resumed->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_SYSTEM, $resumed->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 3);
    }

    public function test_resume_paused_creates_active_row_and_closes_pause_row(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId);

        $result = $this->service->resumePaused(
            currentDoiId: $paused->id,
            resumeStartDate: '2026-03-10',
            qty: 2.00,
            frequencyType: 'daily',
            resumeEndDate: null,
        );

        $closed = $result['closed_current']->fresh();
        $active = $result['active_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_RESUME, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $active->status);
        $this->assertSame('2026-03-10', $active->start_date->toDateString());
        $this->assertNull($active->end_date);
        $this->assertSame('2.00', (string) $active->qty);
        $this->assertSame('daily', $active->frequency_type);
        $this->assertSame($paused->id, $active->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_RESUME, $active->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 2);
    }

    public function test_change_active_to_active_with_end_date_creates_changed_and_restored_rows(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $result = $this->service->changeActiveToActive(
            currentDoiId: $current->id,
            changeStartDate: '2026-03-10',
            changeEndDate: '2026-03-15',
            newQty: 2.00,
            newFrequencyType: 'daily',
        );

        $closed = $result['closed_current']->fresh();
        $changed = $result['changed_doi']->fresh();
        $restored = $result['restored_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_MODIFY, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $changed->status);
        $this->assertSame('2026-03-10', $changed->start_date->toDateString());
        $this->assertSame('2026-03-15', $changed->end_date->toDateString());
        $this->assertSame('2.00', (string) $changed->qty);
        $this->assertSame('daily', $changed->frequency_type);
        $this->assertSame($current->id, $changed->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_MODIFY, $changed->created_from_action);

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $restored->status);
        $this->assertSame('2026-03-16', $restored->start_date->toDateString());
        $this->assertNull($restored->end_date);
        $this->assertSame('1.00', (string) $restored->qty);
        $this->assertSame('daily', $restored->frequency_type);
        $this->assertSame($changed->id, $restored->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_SYSTEM, $restored->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 3);
    }

    public function test_cancel_active_creates_cancelled_row_and_closes_current(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $result = $this->service->cancelActive(
            currentDoiId: $current->id,
            cancelStartDate: '2026-03-10',
        );

        $closed = $result['closed_current']->fresh();
        $cancelled = $result['cancelled_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_CANCEL, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('2026-03-10', $cancelled->start_date->toDateString());
        $this->assertNull($cancelled->end_date);
        $this->assertSame('0.00', (string) $cancelled->qty);
        $this->assertNull($cancelled->frequency_type);
        $this->assertSame($current->id, $cancelled->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_CANCEL, $cancelled->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 2);
    }

    public function test_reactivate_cancelled_creates_active_row_and_closes_cancelled(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $cancelled = $this->createCancelledDoi($draftOrderId);

        $result = $this->service->reactivateCancelled(
            currentDoiId: $cancelled->id,
            reactivateStartDate: '2026-03-10',
            qty: 1.00,
            frequencyType: 'daily',
            reactivateEndDate: null,
        );

        $closed = $result['closed_current']->fresh();
        $active = $result['active_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_REACTIVATE, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $active->status);
        $this->assertSame('2026-03-10', $active->start_date->toDateString());
        $this->assertNull($active->end_date);
        $this->assertSame('1.00', (string) $active->qty);
        $this->assertSame('daily', $active->frequency_type);
        $this->assertSame($cancelled->id, $active->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_REACTIVATE, $active->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 2);
    }

    public function test_pause_active_throws_if_pause_end_before_pause_start(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pause_end_date must be >= pause_start_date');

        $this->service->pauseActive(
            currentDoiId: $current->id,
            pauseStartDate: '2026-03-10',
            pauseEndDate: '2026-03-09',
        );
    }

    public function test_resume_paused_throws_if_qty_is_zero(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('qty must be > 0 for active DOI');

        $this->service->resumePaused(
            currentDoiId: $paused->id,
            resumeStartDate: '2026-03-10',
            qty: 0.00,
            frequencyType: 'daily',
        );
    }

    public function test_change_active_to_active_throws_if_frequency_invalid(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid frequency_type: every_day');

        $this->service->changeActiveToActive(
            currentDoiId: $current->id,
            changeStartDate: '2026-03-10',
            changeEndDate: null,
            newQty: 2.00,
            newFrequencyType: 'every_day',
        );
    }

    public function test_pause_active_throws_if_another_open_doi_exists(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $current = $this->createActiveDoi($draftOrderId);

        $this->createActiveDoi($draftOrderId, [
            'variant_id' => $current->variant_id,
            'start_date' => '2026-03-05',
            'end_date' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Another open DOI exists for this subscription.');

        $this->service->pauseActive(
            currentDoiId: $current->id,
            pauseStartDate: '2026-03-10',
            pauseEndDate: '2026-03-15',
        );
    }

    public function test_resume_paused_throws_if_resume_start_before_current_start(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId, [
            'start_date' => '2026-03-10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new start date cannot be before current DOI start date');

        $this->service->resumePaused(
            currentDoiId: $paused->id,
            resumeStartDate: '2026-03-09',
            qty: 1.00,
            frequencyType: 'daily',
        );
    }
    public function test_cancel_paused_creates_cancelled_row_and_closes_pause_row(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId, [
            'start_date' => '2026-03-01',
            'end_date' => null,
        ]);

        $result = $this->service->cancelPaused(
            currentDoiId: $paused->id,
            cancelStartDate: '2026-03-10',
        );

        $closed = $result['closed_current']->fresh();
        $cancelled = $result['cancelled_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_CANCEL, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('2026-03-10', $cancelled->start_date->toDateString());
        $this->assertNull($cancelled->end_date);
        $this->assertSame('0.00', (string) $cancelled->qty);
        $this->assertNull($cancelled->frequency_type);
        $this->assertSame($paused->id, $cancelled->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_CANCEL, $cancelled->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 2);
    }

    public function test_cancel_paused_throws_if_cancel_start_before_pause_start(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId, [
            'start_date' => '2026-03-10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new start date cannot be before current DOI start date');

        $this->service->cancelPaused(
            currentDoiId: $paused->id,
            cancelStartDate: '2026-03-09',
        );
    }

    public function test_extend_pause_creates_new_paused_row_and_closes_current_pause(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId, [
            'start_date' => '2026-03-01',
            'end_date' => null,
        ]);

        $result = $this->service->extendPause(
            currentDoiId: $paused->id,
            newPauseStartDate: '2026-03-10',
            newPauseEndDate: '2026-03-15',
        );

        $closed = $result['closed_current']->fresh();
        $newPaused = $result['paused_doi']->fresh();

        $this->assertSame('2026-03-09', $closed->end_date->toDateString());
        $this->assertSame(DraftOrderItem::ACTION_PAUSE, $closed->closed_by_action);

        $this->assertSame(DraftOrderItem::STATUS_PAUSED, $newPaused->status);
        $this->assertSame('2026-03-10', $newPaused->start_date->toDateString());
        $this->assertSame('2026-03-15', $newPaused->end_date->toDateString());
        $this->assertSame('0.00', (string) $newPaused->qty);
        $this->assertNull($newPaused->frequency_type);
        $this->assertSame($paused->id, $newPaused->supersedes_doi_id);
        $this->assertSame(DraftOrderItem::ACTION_PAUSE, $newPaused->created_from_action);

        $this->assertDatabaseCount('draft_order_items', 2);
    }

    public function test_extend_pause_throws_if_new_pause_end_before_new_pause_start(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new_pause_end_date must be >= new_pause_start_date');

        $this->service->extendPause(
            currentDoiId: $paused->id,
            newPauseStartDate: '2026-03-10',
            newPauseEndDate: '2026-03-09',
        );
    }

    public function test_extend_pause_throws_if_new_pause_start_before_current_pause_start(): void
    {
        $draftOrderId = $this->createDraftOrder();
        $paused = $this->createPausedDoi($draftOrderId, [
            'start_date' => '2026-03-10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('new start date cannot be before current DOI start date');

        $this->service->extendPause(
            currentDoiId: $paused->id,
            newPauseStartDate: '2026-03-09',
            newPauseEndDate: '2026-03-12',
        );
    }

    public function test_create_active_subscription_row_positive(): void
    {
        $draftOrderId = $this->createDraftOrder();

        $active = $this->createActiveDoi($draftOrderId, [
            'start_date' => '2026-03-01',
            'end_date' => null,
            'qty' => 1.00,
            'frequency_type' => 'daily',
            'status' => DraftOrderItem::STATUS_ACTIVE,
        ])->fresh();

        $this->assertSame(DraftOrderItem::STATUS_ACTIVE, $active->status);
        $this->assertSame('2026-03-01', $active->start_date->toDateString());
        $this->assertNull($active->end_date);
        $this->assertSame('1.00', (string) $active->qty);
        $this->assertSame('daily', $active->frequency_type);

        $this->assertDatabaseCount('draft_order_items', 1);
    }

    public function test_create_active_subscription_row_negative_invalid_frequency(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $draftOrderId = $this->createDraftOrder();

        DraftOrderItem::query()->create([
            'draft_order_id' => $draftOrderId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 8383403720978,
            'variant_id' => 45447791247634,
            'vendor_id' => null,
            'frequency_type' => 'invalid_frequency',
            'qty' => 1.00,
            'unit' => 'pcs',
            'price_snapshot' => 32.00,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => DraftOrderItem::STATUS_ACTIVE,
            'supersedes_doi_id' => null,
            'created_from_action' => null,
            'closed_by_action' => null,
            'meta' => null,
        ]);
    }
}
