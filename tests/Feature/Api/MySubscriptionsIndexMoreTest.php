<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MySubscriptionsIndexMoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function seedBase(User $user): array
    {
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

        // minimal product/variant if tables exist
        if (DB::getSchemaBuilder()->hasTable('products')) {
            $p = ['product_id' => 101, 'created_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('products', 'title')) $p['title'] = 'Test Product';
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

    public function test_cancel_tab_returns_cancelled_items(): void

    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        [$scrId, $dorId] = $this->seedBase($user);

        // active row
        $activeId = DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // cancelled row (latest)
        DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => $activeId,
            'change_action' => 'cancel',
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => null,
            'frequency_type' => null,
            'qty' => 0.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => '2025-12-19',
            'end_date' => null,
            'status' => 'cancelled',
            'meta' => json_encode(['source' => 'pause_cancel']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-subscriptions?tab=cancel&date=2025-12-19');

        $resp->assertOk();
        $json = $resp->json();

        $this->assertNotEmpty($json['data']);
        $this->assertEquals('cancelled', $json['data'][0]['items'][0]['status']);
    }

    public function test_invalid_tab_falls_back_to_active(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        [$scrId, $dorId] = $this->seedBase($user);

        DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-subscriptions?tab=xxx&date=2025-12-19');

        $resp->assertOk();
        $this->assertEquals('active', $resp->json('data.0.items.0.status'));
    }

    public function test_index_filters_start_date_null_or_lte_and_end_date_null_or_gte(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        [$scrId, $dorId] = $this->seedBase($user);

        // should appear (start_date null)
        DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => null,
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // should NOT appear (start_date in future)
        DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 203, // different variant
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => '2025-12-30',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // should NOT appear (end_date in past)
        DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 204,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36,
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-10',
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-subscriptions?tab=active&date=2025-12-19');

        $resp->assertOk();
        $items = $resp->json('data.0.items');

        // only the start_date NULL one should survive
        $this->assertCount(1, $items);
    }
}
