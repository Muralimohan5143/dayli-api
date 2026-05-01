<?php

namespace Tests\Feature;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\SubChangeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerChangesTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('zones')->insert([
            'id' => 1,
            'name' => 'Test Zone',
            'code' => 'ZONE-1',   // 🔥 REQUIRED
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Role::firstOrCreate(['name' => 'zone-manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'workman-delivery-boy', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->operator = User::factory()->create([
            'name' => 'Zone Manager',
            'phone' => '9000000001',
            'zone_id' => 1,
        ]);
        $this->operator->assignRole('zone-manager');

        $this->customer = User::factory()->create([
            'name' => 'DR. Koteswar Rao',
            'phone' => '9885734326',
            'zone_id' => 1,
        ]);
        $this->customer->assignRole('customer');

        $this->seedProduct();
    }

    /** @test */
    public function operator_can_list_customer_subscriptions()
    {
        Sanctum::actingAs($this->operator);

        $this->createActiveSubscriptionForCustomer($this->customer);

        $response = $this->getJson('/api/operator/customer-subscriptions?tab=active&q=Koteswar');

        $response->assertOk()
            ->assertJsonPath('data.0.customer_id', $this->customer->id)
            ->assertJsonPath('data.0.customer_name', 'DR. Koteswar Rao')
            ->assertJsonPath('data.0.product_name', 'Vijaya Gold Milk(500 ml)');
    }

    /** @test */
    public function customer_cannot_list_operator_customer_subscriptions()
    {
        Sanctum::actingAs($this->customer);

        $response = $this->getJson('/api/operator/customer-subscriptions?tab=active');

        $response->assertStatus(403);
    }

    /** @test */
    public function operator_can_add_backdated_subscription_for_customer_and_backfill_is_triggered()
    {

        $this->withoutExceptionHandling();
        Sanctum::actingAs($this->operator);

        Carbon::setTestNow(Carbon::parse('2026-04-26'));

        Artisan::shouldReceive('call')
            ->once()
            ->with('dayli:generate-daily-orders', [
                '--from' => '2026-04-22',
                '--to' => '2026-04-26',
                '--customer_id' => $this->customer->id,
            ])
            ->andReturn(0);

        $response = $this->postJson('/api/my-subscriptions/store-from-selection', [
            'customer_id' => $this->customer->id,
            'party_type' => 'consumer',
            'items' => [
                [
                    'subscription_type_id' => 3,
                    'sub_type_id' => 31,
                    'product_id' => 10339468935442,
                    'variant_id' => 52217769394450,
                    'price' => 10,
                    'mrp' => 10,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'cost_price' => 0,
                    'qty' => 4,
                    'unit' => 'ml',
                    'frequency' => 'daily',
                    'start_date' => '2026-04-22',
                    'end_date' => null,
                ],
            ],
        ]);

        $response->dump();
        if ($response->status() !== 201) {
            dd(
                $response->status(),
                $response->headers->all(),
                $response->getContent()
            );
        }

        $response->assertStatus(201);
        $this->assertDatabaseHas('sub_change_requests', [
            'for_user_id' => $this->customer->id,
            'by_user_id' => $this->operator->id,
            'party_type' => 'consumer',
            'change_reason' => 'operator_assisted',
        ]);

        $this->assertDatabaseHas('draft_order_items', [
            'product_id' => 10339468935442,
            'variant_id' => 52217769394450,
            'qty' => 4,
            'unit' => 'ml',
            'frequency_type' => 'daily',
            'start_date' => '2026-04-22',
            'status' => 'active',
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function customer_cannot_add_subscription_for_another_customer()
    {
        $otherCustomer = User::factory()->create([
            'zone_id' => 1,
        ]);

        $otherCustomer->assignRole('customer');

        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/my-subscriptions/store-from-selection', [
            'customer_id' => $otherCustomer->id,
            'party_type' => 'consumer',
            'items' => [
                [
                    'subscription_type_id' => 3,
                    'sub_type_id' => 31,
                    'product_id' => 10339468935442,
                    'variant_id' => 52217769394450,
                    'price' => 10,
                    'qty' => 1,
                    'unit' => 'pcs',
                    'frequency' => 'daily',
                    'start_date' => now()->toDateString(),
                ],
            ],
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function operator_can_pause_customer_subscription()
    {
        Sanctum::actingAs($this->operator);

        $item = $this->createActiveSubscriptionForCustomer($this->customer);

        $response = $this->postJson('/api/my-subscriptions/change', [
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'original_item_id' => $item->id,
                    'qty' => 0,
                    'unit' => 'pcs',
                    'frequency_type' => null,
                    'start_date' => '2026-04-27',
                    'end_date' => '2026-04-30',
                    'price' => 10,
                    'mrp' => 10,
                    'cost_price' => 0,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'action' => 'pause',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('draft_order_items', [
            'draft_order_id' => $item->draft_order_id,
            'original_item_id' => $item->id,
            'change_action' => 'pause',
            'status' => 'paused',
            'start_date' => '2026-04-27',
            'end_date' => '2026-04-30',
        ]);
    }

    private function seedProduct(): void
    {
        DB::table('products')->insertOrIgnore([
            'product_id' => 10339468935442,
            'title' => 'Vijaya Gold Milk(500 ml)',
            'vendor' => 'Dayli',
            'product_type' => 'Milk & Dairy',
            'handle' => 'vijaya-gold-milk-500ml',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('variants')->insertOrIgnore([
            'variant_id' => 52217769394450,
            'product_id' => 10339468935442,
            'title' => 'Vijaya Gold Milk(500 ml)',
            'sku' => 'VG-500',
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createActiveSubscriptionForCustomer(User $customer): DraftOrderItem
    {
        $scr = SubChangeRequest::create([
            'for_user_id' => $customer->id,
            'by_user_id' => $customer->id,
            'party_type' => 'consumer',
            'zone_id' => 1,
            'subscription_type_id' => 3,
            'subtypes_json' => json_encode(['selected_sub_type_ids' => [31]]),
            'invoice_cycle' => 'monthly',
            'change_reason' => 'self_service',
            'action' => 'create',
            'status' => 'pending',
            'priority' => 3,
        ]);

        $draft = DraftOrder::create([
            'change_request_id' => $scr->id,
            'customer_id' => $customer->shopify_customer_id ?? null,
            'zone_id' => 1,
            'cadence' => 'daily',
            'invoice_cycle' => 'monthly',
            'start_date' => '2026-04-22',
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'title' => 'Test draft order',
        ]);

        $scr->update(['draft_order_id' => $draft->id]);

        return DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'product_id' => 10339468935442,
            'variant_id' => 52217769394450,
            'frequency_type' => 'daily',
            'qty' => 1,
            'unit' => 'pcs',
            'price_snapshot' => 10,
            'start_date' => '2026-04-22',
            'status' => 'active',
            'meta' => [
                'mrp' => 10,
                'cost_price' => 0,
                'discount_amount' => 0,
                'discount_percent' => 0,
            ],
        ]);
    }
}
