<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * `/` serves the SPA shell document. The Vite build (public/build) is a
     * build artifact that is not part of the backend-only test environment:
     * when it is absent the route intentionally aborts(500), so this test
     * only asserts the positive case when the build is present (e.g. after
     * `npm run build` in the frontend workspace).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        if (! is_file(public_path('build/index.html'))) {
            $this->markTestSkipped('Vite SPA build (public/build/index.html) is not present in a backend-only checkout.');
        }

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('/build/assets/', false);
    }

    /**
     * The API health probe must answer 200 with or without a frontend build.
     */
    public function test_api_health_endpoint_responds(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ok');
    }
}
