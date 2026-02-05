<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionChangeMoreTest extends TestCase
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

    private function seedActive(User $user, int $variantId = 202): int
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

        if (DB::getSchemaBuilder()->hasTable('products')) {
            $p = ['product_id' => 101, 'created_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('products', 'title')) $p['title'] = 'Test Product';
            if (DB::getSchemaBuilder()->hasColumn('products', 'product_type')) $p['product_type'] = 'Milk';
            DB::table('products')->updateOrInsert(['product_id' => 101], $p);
        }
        if (DB::getSchemaBuilder()->hasTable('variants')) {
            $v = ['variant_id' => $variantId, 'created_at' => now(), 'updated_at' => now()];
            if (DB::getSchemaBuilder()->hasColumn('variants', 'product_id')) $v['product_id'] = 101;
            DB::table('variants')->updateOrInsert(['variant_id' => $variantId], $v);
        }

        return DB::table('draft_order_items')->insertGetId([
            'draft_order_id' => $dorId,
            'original_item_id' => null,
            'change_action' => null,
            'product_id' => 101,
            'variant_id' => $variantId,
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

    public function test_cancel_requires_start_date(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $originalId = $this->seedActive($user);

        $payload = [
            'items' => [[
                'original_item_id' => $originalId,
                'action' => 'cancel',
                // start_date missing
                'qty' => 0,
                'unit' => 'L',
            ]],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload)
            ->assertStatus(422);
    }

    public function test_previous_row_is_closed_end_date_is_start_date_minus_one(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $originalId = $this->seedActive($user);

        $payload = [
            'items' => [[
                'original_item_id' => $originalId,
                'action' => 'pause',
                'start_date' => '2025-12-19',
                'end_date' => '2025-12-25',
                'qty' => 0,
                'unit' => 'L',
                'price' => 36,
                'mrp' => 40,
            ]],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload)
            ->assertOk();

        $old = DB::table('draft_order_items')->where('id', $originalId)->first();
        $this->assertEquals('2025-12-18', $old->end_date);
    }

    public function test_chain_is_sequential_new_row_original_item_id_equals_current_id(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $originalId = $this->seedActive($user);

        // first pause
        $this->actingAs($user, 'sanctum')->postJson('/api/my-subscriptions/change', [
            'items' => [[
                'original_item_id' => $originalId,
                'action' => 'pause',
                'start_date' => '2025-12-19',
                'end_date' => '2025-12-25',
                'qty' => 0,
                'unit' => 'L',
                'price' => 36,
                'mrp' => 40,
            ]],
        ])->assertOk();

        $pauseRow = DB::table('draft_order_items')
            ->where('change_action', 'pause')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($pauseRow);

        // now cancel, controller should pick "current" as latest row and link to it
        $this->actingAs($user, 'sanctum')->postJson('/api/my-subscriptions/change', [
            'items' => [[
                'original_item_id' => $originalId,
                'action' => 'cancel',
                'start_date' => '2025-12-20',
                'qty' => 0,
                'unit' => 'L',
                'price' => 36,
                'mrp' => 40,
            ]],
        ])->assertOk();

        $cancelRow = DB::table('draft_order_items')
            ->where('change_action', 'cancel')
            ->orderByDesc('id')
            ->first();

        // ✅ IMPORTANT: new cancel row must point to pauseRow.id (current.id)
        $this->assertEquals($pauseRow->id, $cancelRow->original_item_id);
    }

    public function test_multiple_items_payload_updates_two_variants(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $id1 = $this->seedActive($user, 202);
        $id2 = $this->seedActive($user, 303);

        $payload = [
            'items' => [
                [
                    'original_item_id' => $id1,
                    'action' => 'pause',
                    'start_date' => '2025-12-19',
                    'end_date' => '2025-12-25',
                    'qty' => 0,
                    'unit' => 'L',
                ],
                [
                    'original_item_id' => $id2,
                    'action' => 'cancel',
                    'start_date' => '2025-12-19',
                    'end_date' => '2025-12-19', // ✅ REQUIRED by your validation
                    'qty' => 0,
                    'unit' => 'L',
                ],
            ],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/my-subscriptions/change', $payload)
            ->assertOk();

        $this->assertDatabaseHas('draft_order_items', ['variant_id' => 202, 'status' => 'paused']);
        $this->assertDatabaseHas('draft_order_items', ['variant_id' => 303, 'status' => 'cancelled']);
    }

    public function test_unauthenticated_change_returns_401(): void
    {
        $payload = [
            'items' => [[
                'original_item_id' => 1,
                'action' => 'pause',
                'start_date' => '2025-12-19',
                'end_date' => '2025-12-25',
                'qty' => 0,
                'unit' => 'L',
            ]],
        ];

        $this->postJson('/api/my-subscriptions/change', $payload)->assertStatus(401);
    }
}
