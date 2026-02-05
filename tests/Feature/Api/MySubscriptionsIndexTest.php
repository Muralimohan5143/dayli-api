<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MySubscriptionsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints(); // ✅ DB-driver safe
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints(); // ✅ DB-driver safe
        parent::tearDown();
    }

    private function seedBaseChain(User $user, string $asOfDate = '2025-12-19'): array
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

        // Optional: only if these tables exist in your test DB
        if (DB::getSchemaBuilder()->hasTable('products')) {
            $productData = [
                'product_id' => 101,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // ✅ set required fields only if they exist
            if (DB::getSchemaBuilder()->hasColumn('products', 'title')) {
                $productData['title'] = 'Test Product';
            }
            if (DB::getSchemaBuilder()->hasColumn('products', 'product_type')) {
                $productData['product_type'] = 'Milk';
            }

            DB::table('products')->updateOrInsert(
                ['product_id' => 101],
                $productData
            );
        }
        if (DB::getSchemaBuilder()->hasTable('variants')) {
            $variantData = [
                'variant_id' => 202,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // ✅ required in your schema
            if (DB::getSchemaBuilder()->hasColumn('variants', 'product_id')) {
                $variantData['product_id'] = 101; // must match products.product_id
            }

            // Optional common columns (safe)
            if (DB::getSchemaBuilder()->hasColumn('variants', 'title')) {
                $variantData['title'] = 'Test Variant';
            }

            DB::table('variants')->updateOrInsert(
                ['variant_id' => 202],
                $variantData
            );
        }


        $activeId = DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => 999,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36.00,
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode(['mrp' => 40]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pausedId = DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => $activeId,
            'change_action' => 'pause',
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => 998,
            'frequency_type' => null,
            'qty' => 0.00,
            'unit' => 'L',
            'price_snapshot' => 36.00,
            'start_date' => $asOfDate,
            'end_date' => '2025-12-25',
            'status' => 'paused',
            'meta' => json_encode(['source' => 'pause_cancel']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $futureActiveId = DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => $activeId,
            'change_action' => 'resume',
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => 997,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36.00,
            'start_date' => '2025-12-26',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$scrId, $dorId, $activeId, $pausedId, $futureActiveId];
    }

    public function test_paused_tab_returns_paused_items_as_of_date(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->seedBaseChain($user, '2025-12-19');

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-subscriptions?tab=paused&date=2025-12-19');

        $resp->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['type_name', 'product_count', 'total_qty', 'items'],
                ],
            ]);

        $json = $resp->json();
        $this->assertNotEmpty($json['data']);

        $items = $json['data'][0]['items'];
        $this->assertNotEmpty($items);
        $this->assertEquals('paused', $items[0]['status']);
    }

    public function test_active_tab_returns_active_items_only_as_of_date(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

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

        DB::table('draft_order_items')->insert([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => 202,
            'vendor_id' => 999,
            'frequency_type' => 'daily',
            'qty' => 1.00,
            'unit' => 'L',
            'price_snapshot' => 36.00,
            'start_date' => '2025-12-01',
            'end_date' => null,
            'status' => 'active',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-subscriptions?tab=active&date=2025-12-19');

        $resp->assertOk();

        $json = $resp->json();
        $this->assertNotEmpty($json['data']);

        $items = $json['data'][0]['items'];
        $this->assertNotEmpty($items);
        $this->assertEquals('active', $items[0]['status']);
    }

    public function test_unauthenticated_get_returns_401(): void
    {
        $this->getJson('/api/my-subscriptions?tab=active&date=2025-12-19')
            ->assertStatus(401);
    }
}
