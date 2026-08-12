<?php

namespace Tests\Unit\Services;

use App\Models\TaxProfile;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaxService::class);
    }

    public function test_returns_zero_tax_with_no_profile(): void
    {
        $result = $this->service->calculate(null, 100);

        $this->assertEquals(0.0, $result['tax_amount']);
        $this->assertNull($result['tax_profile']);
    }

    public function test_calculates_exclusive_tax(): void
    {
        $profile = TaxProfile::factory()->create([
            'name' => 'VAT 16%',
            'rate' => 16,
            'type' => 'exclusive',
            'is_default' => true,
            'is_active' => true,
        ]);

        $result = $this->service->calculate($profile->id, 100);

        $this->assertEquals(16.0, $result['tax_amount']);
        $this->assertNotNull($result['tax_profile']);
    }

    public function test_calculates_inclusive_tax(): void
    {
        $profile = TaxProfile::factory()->create([
            'name' => 'GST 10%',
            'rate' => 10,
            'type' => 'inclusive',
            'is_default' => false,
            'is_active' => true,
        ]);

        $result = $this->service->calculate($profile->id, 100);

        $this->assertEquals(9.09, $result['tax_amount']);
    }

    public function test_falls_back_to_default_profile(): void
    {
        TaxProfile::factory()->create([
            'name' => 'Default VAT',
            'rate' => 16,
            'type' => 'exclusive',
            'is_default' => true,
            'is_active' => true,
        ]);

        $result = $this->service->calculate(null, 100);

        $this->assertEquals(16.0, $result['tax_amount']);
        $this->assertNotNull($result['tax_profile']);
        $this->assertEquals('Default VAT', $result['tax_profile']->name);
    }

    public function test_returns_zero_when_no_default_profile(): void
    {
        $result = $this->service->calculate(null, 100);

        $this->assertEquals(0.0, $result['tax_amount']);
        $this->assertNull($result['tax_profile']);
    }

    public function test_handles_zero_rate(): void
    {
        $profile = TaxProfile::factory()->create([
            'name' => 'Zero Rated',
            'rate' => 0,
            'type' => 'exclusive',
            'is_active' => true,
        ]);

        $result = $this->service->calculate($profile->id, 100);

        $this->assertEquals(0.0, $result['tax_amount']);
    }

    public function test_uses_named_profile_over_default(): void
    {
        TaxProfile::factory()->create([
            'name' => 'Default VAT',
            'rate' => 16,
            'type' => 'exclusive',
            'is_default' => true,
            'is_active' => true,
        ]);

        $custom = TaxProfile::factory()->create([
            'name' => 'Reduced VAT',
            'rate' => 8,
            'type' => 'exclusive',
            'is_default' => false,
            'is_active' => true,
        ]);

        $result = $this->service->calculate($custom->id, 100);

        $this->assertEquals(8.0, $result['tax_amount']);
    }
}
