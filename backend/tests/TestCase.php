<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Execute a request and then drop the resolved auth guards.
     *
     * Production boots a fresh application for every request, so an
     * authenticated guard never outlives its request. Tests reuse a single
     * container, and Sanctum's RequestGuard caches the resolved user on the
     * guard instance, which would otherwise keep a revoked token (or a stale
     * user) valid for the rest of the test. Clearing the guards after each
     * request reproduces real per-request behaviour.
     *
     * Consequence: every request must be authenticated explicitly, either
     * through `withToken(...)` or by calling `Sanctum::actingAs(...)` again
     * immediately before it.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $this->app['auth']->forgetGuards();

        return $response;
    }
}
