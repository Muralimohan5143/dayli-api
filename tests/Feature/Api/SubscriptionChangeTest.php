<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints(); // ✅ works across DB drivers
    }

    protected function tearDown(): void
    {
        Schema::enableForeignKeyConstraints(); // ✅ works across DB drivers
        parent::tearDown();
    }



    private function seedSingleActiveItem(User $user): int
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

        // ✅ Products table (title required in your DB)
        if (DB::getSchemaBuilder()->hasTable('products')) {
            $productData = [
                'product_id' => 101,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (DB::getSchemaBuilder()->hasColumn('products', 'title')) {
                $productData['title'] = 'Test Product';
            }
            if (DB::getSchemaBuilder()->hasColumn('products', 'product_type')) {
                $productData['product_type'] = 'Milk';
            }

            DB::table('products')->updateOrInsert(['product_id' => 101], $productData);
        }

        // ✅ Variants table (product_id required in your DB)
        if (DB::getSchemaBuilder()->hasTable('variants')) {
            $variantData = [
                'variant_id' => 202,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (DB::getSchemaBuilder()->hasColumn('variants', 'product_id')) {
                $variantData['product_id'] = 101;
            }
            if (DB::getSchemaBuilder()->hasColumn('variants', 'title')) {
                $variantData['title'] = 'Test Variant';
            }

            DB::table('variants')->updateOrInsert(['variant_id' => 202], $variantData);
        }


        return DB::table('draft_order_items')->insertGetId([
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
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pause_creates_new_row_with_status_paused(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create(); // ✅ IDE fixed
        $originalId = $this->seedSingleActiveItem($user);

        $payload = [
            'items' => [
                [
                    'original_item_id' => $originalId,
                    'action' => 'pause',
                    'start_date' => '2025-12-19',
                    'end_date' => '2025-12-25',
                    'qty' => 0,
                    'unit' => 'L',
                    'price' => 36,
                    'mrp' => 40,
                ],
            ],
        ];

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload);

        $resp->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('draft_order_items', [
            'original_item_id' => $originalId,
            'change_action' => 'pause',
            'status' => 'paused',
            'start_date' => '2025-12-19',
            'end_date' => '2025-12-25',
        ]);
    }

    public function test_cancel_creates_new_row_with_status_cancelled_and_end_date_null(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $originalId = $this->seedSingleActiveItem($user);

        $payload = [
            'items' => [
                [
                    'original_item_id' => $originalId,
                    'action' => 'cancel',
                    'start_date' => '2025-12-19',
                    'qty' => 0,
                    'unit' => 'L',
                    'price' => 36,
                    'mrp' => 40,
                ],
            ],
        ];

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload);

        $resp->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('draft_order_items', [
            'original_item_id' => $originalId,
            'change_action' => 'cancel',
            'status' => 'cancelled',
            'start_date' => '2025-12-19',
        ]);

        $row = DB::table('draft_order_items')
            ->where('original_item_id', $originalId)
            ->where('change_action', 'cancel')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->end_date);
    }

    public function test_pause_requires_end_date(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $originalId = $this->seedSingleActiveItem($user);

        $payload = [
            'items' => [
                [
                    'original_item_id' => $originalId,
                    'action' => 'pause',
                    'start_date' => '2025-12-19',
                    // end_date missing
                    'qty' => 0,
                    'unit' => 'L',
                ],
            ],
        ];

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload);

        $resp->assertStatus(422);
    }
}
