<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_200_when_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status', 'latency_ms'],
                'redis' => ['status', 'latency_ms'],
                'queue' => ['status', 'pending_jobs'],
            ],
        ]);
        $response->assertJsonPath('status', 'healthy');
    }

    public function test_health_check_does_not_require_auth(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
    }

    public function test_health_check_timestamp_is_valid_iso8601(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $timestamp = $response->json('timestamp');
        $this->assertIsNumeric(strtotime($timestamp), 'Timestamp should be valid ISO 8601');
    }
}
