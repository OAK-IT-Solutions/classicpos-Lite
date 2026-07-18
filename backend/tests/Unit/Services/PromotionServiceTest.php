<?php

namespace Tests\Unit\Services;

use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PromotionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PromotionService::class);
    }

    public function test_returns_no_discount_when_no_code(): void
    {
        $result = $this->service->validateAndApply(null, 100);

        $this->assertFalse($result['applied']);
        $this->assertEquals(0.0, $result['discount']);
    }

    public function test_returns_no_discount_for_invalid_code(): void
    {
        $result = $this->service->validateAndApply('NONEXISTENT', 100);

        $this->assertFalse($result['applied']);
        $this->assertEquals(0.0, $result['discount']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_applies_percentage_discount(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 0,
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('TEST10', 100);

        $this->assertTrue($result['applied']);
        $this->assertEquals(10.0, $result['discount']);
    }

    public function test_applies_flat_discount(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'FLAT5',
            'type' => 'flat',
            'value' => 5,
            'min_order_amount' => 0,
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('FLAT5', 100);

        $this->assertTrue($result['applied']);
        $this->assertEquals(5.0, $result['discount']);
    }

    public function test_respects_max_discount_cap(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'BIG50',
            'type' => 'percentage',
            'value' => 50,
            'max_discount_amount' => 20,
            'min_order_amount' => 0,
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('BIG50', 200);

        $this->assertTrue($result['applied']);
        $this->assertEquals(20.0, $result['discount']);
    }

    public function test_rejects_when_min_order_not_met(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'MIN100',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 100,
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('MIN100', 50);

        $this->assertFalse($result['applied']);
        $this->assertEquals(0.0, $result['discount']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_rejects_expired_code(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'EXPIRED',
            'type' => 'flat',
            'value' => 10,
            'valid_from' => now()->subMonths(3),
            'valid_until' => now()->subMonth(),
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('EXPIRED', 100);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_rejects_inactive_code(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'INACTIVE',
            'type' => 'flat',
            'value' => 10,
            'is_active' => false,
            'usage_limit' => null,
        ]);

        $result = $this->service->validateAndApply('INACTIVE', 100);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_increments_used_count(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'COUNTME',
            'type' => 'flat',
            'value' => 5,
            'usage_limit' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->service->validateAndApply('COUNTME', 100);

        $this->assertEquals(1, $promo->fresh()->used_count);
    }

    public function test_rejects_when_usage_limit_reached(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'EXHAUSTED',
            'type' => 'flat',
            'value' => 5,
            'usage_limit' => 5,
            'used_count' => 5,
            'is_active' => true,
        ]);

        $result = $this->service->validateAndApply('EXHAUSTED', 100);

        $this->assertFalse($result['applied']);
        $this->assertArrayHasKey('error', $result);
    }
}
