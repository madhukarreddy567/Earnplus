<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_ok_with_database_status(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJson([
            'app' => 'EarnPlus',
            'status' => 'ok',
            'database' => 'ok',
        ]);
        $this->assertArrayHasKey('time', $response->json());
    }
}
