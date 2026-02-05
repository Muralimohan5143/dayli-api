<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionsActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        // Fix "today" for deterministic pause/resume effectiveStartDate()
        Carbon::setTestNow(Carbon::parse('2025-12-19 08:00:00'));
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedBase(User $user): array
    {
        // sub_change_requests + draft_orders exist in your app (same as other tests)
        $scrId = DB::table('sub_change_requests')->insertGetId([
            'for_user_id' => $user->id,
            'by_user_id'  => $user->id,
            'invoice_cycle' => 'monthly',
            'change_reason' => 'self_service',
            'action' => 'create',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dorId = DB::table('draft_orders')->insertGetId([
            'change_request_id' => $scrId,
            'customer_id' => $user->id,
            'vendor_id' => 999,
            'zone_id' => null,
            'cadence' => 'daily',
            'invoice_cycle' => 'monthly',
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Minimal product/variant rows if these tables exist
        if (DB::getSchemaBuilder()->hasTable('products')) {
            $p = ['product_id' => 101, 'created_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('products', 'title')) $p['title'] = 'Vijaya Gold Milk';
            if (DB::getSchemaBuilder()->hasColumn('products', 'product_type')) $p['product_type'] = 'Milk';
            if (DB::getSchemaBuilder()->hasColumn('products', 'img_src')) $p['img_src'] = '';
            DB::table('products')->updateOrInsert(['product_id' => 101], $p);
        }

        if (DB::getSchemaBuilder()->hasTable('variants')) {
            $v = ['variant_id' => 202, 'created_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('variants', 'product_id')) $v['product_id'] = 101;
            DB::table('variants')->updateOrInsert(['variant_id' => 202], $v);
        }

        return [$scrId, $dorId];
    }

    private function seedItem(User $user, array $overrides = []): int
    {
        [, $dorId] = $this->seedBase($user);

        $data = array_merge([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36.00,
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([
                // snapshot exists so resume/restart can restore defaults
                'snapshot' => [
                    'qty' => 2.0,
                    'frequency' => 'daily',
                    'unit' => 'L',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('draft_order_items')->insertGetId($data);
    }

    public function test_pause_requires_auth(): void
    {
        $user = User::factory()->create();
        $itemId = $this->seedItem($user);

        $this->postJson("/api/my-subscriptions/items/{$itemId}/pause", [])
            ->assertStatus(401);
    }

    public function test_actions_enforce_ownership_403(): void
    {
        $owner = User::factory()->create();
        /** @var \App\Models\User $other */
        $other = User::factory()->create();

        $itemId = $this->seedItem($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/pause", [])
            ->assertStatus(403);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/cancel", ['start_date' => '2025-12-22'])
            ->assertStatus(403);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/resume", [])
            ->assertStatus(403);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/restart", [])
            ->assertStatus(403);
    }

    public function test_pause_creates_new_paused_row_and_closes_previous_row(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $itemId = $this->seedItem($user, [
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
        ]);

        // pause: end_date optional
        $resp = $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/pause", []);

        $resp->assertOk()->assertJsonPath('ok', true);

        // Old row should be closed up to 2025-12-19 (tomorrow-1)
        $old = DB::table('draft_order_items')->where('id', $itemId)->first();
        $this->assertEquals('2025-12-19', $old->end_date);

        // New row: paused, start_date tomorrow, original_item_id = old.id
        $new = DB::table('draft_order_items')->orderByDesc('id')->first();
        $this->assertEquals($itemId, $new->original_item_id);
        $this->assertEquals('pause', $new->change_action);
        $this->assertEquals('paused', $new->status);
        $this->assertEquals('2025-12-20', $new->start_date);
        $this->assertNull($new->frequency_type);
        $this->assertEquals('0.00', (string)$new->qty);
    }

    public function test_cancel_requires_start_date_422(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $itemId = $this->seedItem($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_cancel_creates_new_cancelled_row_and_closes_previous_row(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $itemId = $this->seedItem($user, [
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$itemId}/cancel", [
                'start_date' => '2025-12-22',
            ]);

        $resp->assertOk()->assertJsonPath('ok', true);

        // Old row end_date = start_date-1 => 2025-12-21
        $old = DB::table('draft_order_items')->where('id', $itemId)->first();
        $this->assertEquals('2025-12-21', $old->end_date);

        $new = DB::table('draft_order_items')->orderByDesc('id')->first();
        $this->assertEquals($itemId, $new->original_item_id);
        $this->assertEquals('cancel', $new->change_action);
        $this->assertEquals('cancelled', $new->status);
        $this->assertEquals('2025-12-22', $new->start_date);
        $this->assertNull($new->end_date);
        $this->assertNull($new->frequency_type);
        $this->assertEquals('0.00', (string)$new->qty);
    }

    public function test_resume_creates_new_active_row_using_snapshot_defaults_and_starts_tomorrow(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // Create a paused item (the "base" passed to resume)
        $pausedId = $this->seedItem($user, [
            'status' => 'paused',
            'change_action' => 'pause',
            'start_date' => '2025-12-10',
            'end_date' => null,
            'qty' => 0,
            'frequency_type' => null,
            'meta' => json_encode([
                'snapshot' => [
                    'qty' => 2.0,
                    'frequency' => 'daily',
                    'unit' => 'L',
                ],
            ]),
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$pausedId}/resume", []);

        $resp->assertOk()->assertJsonPath('ok', true);

        // paused row should close to today (newStart-1, newStart=tomorrow)
        $old = DB::table('draft_order_items')->where('id', $pausedId)->first();
        $this->assertEquals('2025-12-19', $old->end_date);

        $new = DB::table('draft_order_items')->orderByDesc('id')->first();
        $this->assertEquals($pausedId, $new->original_item_id);
        $this->assertEquals('resume', $new->change_action);
        $this->assertEquals('active', $new->status);
        $this->assertEquals('2025-12-20', $new->start_date);
        $this->assertEquals('2.00', (string)$new->qty);
        $this->assertEquals('daily', $new->frequency_type);
        $this->assertEquals('L', $new->unit);
    }

    public function test_restart_allowed_only_for_cancelled_items(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $activeId = $this->seedItem($user, ['status' => 'active']);
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$activeId}/restart", [])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $cancelledId = $this->seedItem($user, [
            'status' => 'cancelled',
            'change_action' => 'cancel',
            'qty' => 0,
            'frequency_type' => null,
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson("/api/my-subscriptions/items/{$cancelledId}/restart", []);

        $resp->assertOk()->assertJsonPath('ok', true);

        $new = DB::table('draft_order_items')->orderByDesc('id')->first();
        $this->assertEquals($cancelledId, $new->original_item_id);
        $this->assertEquals('restart', $new->change_action);
        $this->assertEquals('active', $new->status);
        $this->assertEquals('2025-12-20', $new->start_date); // tomorrow
    }
}
