<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The health probe is the readiness signal of the application: an
 * unreachable database means the session store and every API query fail, so
 * the probe must report that state truthfully (HTTP 503) instead of
 * answering 200 and letting traffic reach a broken service.
 */
class HealthReadinessTest extends TestCase
{
    public function test_health_reports_ok_when_the_database_is_reachable(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ok');
        $response->assertJsonPath('data.database', 'ok');
    }

    public function test_health_reports_503_when_the_database_is_unreachable(): void
    {
        // A deliberately unreachable connection (closed port) reproduces the
        // production failure mode without touching any real database.
        config([
            'database.default' => 'audit_broken',
            'database.connections.audit_broken' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 'sora_audit_missing',
                'username' => 'sora_audit',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503);
        $response->assertJsonPath('data.status', 'unhealthy');
        $response->assertJsonPath('data.database', 'unavailable');
    }
}
