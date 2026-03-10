<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test simple health check endpoint returns 200.
     */
    public function test_simple_health_check_returns_success(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok'
            ])
            ->assertJsonStructure([
                'status',
                'timestamp'
            ]);
    }

    /**
     * Test detailed health check endpoint returns comprehensive data.
     */
    public function test_detailed_health_check_returns_all_checks(): void
    {
        $response = $this->get('/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['status'],
                    'cache' => ['status'],
                    'redis' => ['status'],
                    'storage' => ['status'],
                ]
            ]);
    }

    /**
     * Test detailed health check shows OK status for all components.
     */
    public function test_detailed_health_check_shows_healthy_status(): void
    {
        $response = $this->get('/health/detailed');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals('ok', $data['checks']['database']['status']);
        $this->assertEquals('ok', $data['checks']['cache']['status']);
        $this->assertEquals('ok', $data['checks']['redis']['status']);
        $this->assertEquals('ok', $data['checks']['storage']['status']);
    }

    /**
     * Test health check includes response times.
     */
    public function test_health_check_includes_response_times(): void
    {
        $response = $this->get('/health/detailed');

        $data = $response->json();

        $this->assertArrayHasKey('response_time_ms', $data['checks']['database']);
        $this->assertArrayHasKey('response_time_ms', $data['checks']['cache']);
        $this->assertArrayHasKey('response_time_ms', $data['checks']['redis']);
        $this->assertArrayHasKey('response_time_ms', $data['checks']['storage']);

        // Response times should be positive numbers
        $this->assertGreaterThan(0, $data['checks']['database']['response_time_ms']);
    }

    /**
     * Test health check does not require authentication.
     */
    public function test_health_check_does_not_require_authentication(): void
    {
        // Should work without being authenticated
        $response = $this->get('/health');
        $response->assertStatus(200);

        $response = $this->get('/health/detailed');
        $response->assertStatus(200);
    }
}
