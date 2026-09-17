<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * Test health endpoint returns HTTP 200 and standard JSON response.
     */
    public function test_health_endpoint_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Application is operational',
                'data' => [
                    'status' => 'healthy',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'timestamp',
                ],
                'message',
            ]);
    }

    /**
     * Test non-existent API route returns structured 404 JSON response.
     */
    public function test_non_existent_api_route_returns_structured_404(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }
}
