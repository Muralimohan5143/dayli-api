<?php

namespace Tests\Feature;

use App\Models\DeliveryFeeRule;
use App\Services\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DeliveryFeeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DeliveryFeeService::class);
    }

    /** @test */
    public function it_returns_zero_for_newspaper_fixed_fee_rule(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 1001,
            'variant_id'  => null,
            'fixed_fee'   => 0,
            'formula_fee' => null,
            'priority'    => 10,
            'is_active'   => true,
        ]);

        $fee = $this->service->calculate(1001, null, 10);

        $this->assertSame(0.0, $fee);
    }

    /** @test */
    public function it_returns_one_for_fixed_fee_product_rule(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 1002,
            'variant_id'  => null,
            'fixed_fee'   => 1,
            'formula_fee' => null,
            'priority'    => 10,
            'is_active'   => true,
        ]);

        $fee = $this->service->calculate(1002, null, 7);

        $this->assertSame(1.0, $fee);
    }

    /** @test */
    public function it_evaluates_formula_for_qty_below_25(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 2001,
            'variant_id'  => null,
            'fixed_fee'   => null,
            'formula_fee' => 'qty < 25 ? 2 * qty : 50 * floor(qty / 25)',
            'priority'    => 1,
            'is_active'   => true,
        ]);

        $fee = $this->service->calculate(2001, null, 10);

        $this->assertSame(20.0, $fee);
    }

    /** @test */
    public function it_evaluates_formula_for_qty_25_and_above(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 2001,
            'variant_id'  => null,
            'fixed_fee'   => null,
            'formula_fee' => 'qty < 25 ? 2 * qty : 50 * floor(qty / 25)',
            'priority'    => 1,
            'is_active'   => true,
        ]);

        $fee25 = $this->service->calculate(2001, null, 25);
        $fee30 = $this->service->calculate(2001, null, 30);
        $fee60 = $this->service->calculate(2001, null, 60);

        $this->assertSame(50.0, $fee25);
        $this->assertSame(50.0, $fee30);
        $this->assertSame(100.0, $fee60);
    }

    /** @test */
    public function variant_rule_overrides_product_rule(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 2001,
            'variant_id'  => null,
            'fixed_fee'   => null,
            'formula_fee' => 'qty < 25 ? 2 * qty : 50 * floor(qty / 25)',
            'priority'    => 1,
            'is_active'   => true,
        ]);

        DeliveryFeeRule::create([
            'product_id'  => 2001,
            'variant_id'  => 3001,
            'fixed_fee'   => 5,
            'formula_fee' => null,
            'priority'    => 100,
            'is_active'   => true,
        ]);

        $fee = $this->service->calculate(2001, 3001, 80);

        $this->assertSame(5.0, $fee);
    }

    /** @test */
    public function inactive_rule_is_ignored(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 2001,
            'variant_id'  => null,
            'fixed_fee'   => 10,
            'formula_fee' => null,
            'priority'    => 1,
            'is_active'   => false,
        ]);

        $fee = $this->service->calculate(2001, null, 10);

        $this->assertSame(0.0, $fee);
    }

    /** @test */
    public function it_throws_exception_for_unsafe_formula(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        DeliveryFeeRule::create([
            'product_id'  => 9999,
            'variant_id'  => null,
            'fixed_fee'   => null,
            'formula_fee' => 'phpinfo()',
            'priority'    => 1,
            'is_active'   => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsafe formula detected.');

        $this->service->calculate(9999, null, 10);
    }

    /** @test */
    public function it_returns_zero_when_no_rule_exists(): void
    {
        $this->assertTrue(true);
        $this->assertDatabaseCount('delivery_fee_rules', 0);

        $fee = $this->service->calculate(123456, null, 10);

        $this->assertSame(0.0, $fee);
    }
}
