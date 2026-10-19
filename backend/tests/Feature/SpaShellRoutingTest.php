<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public SPA is served by Laravel for `/` and for every client-side deep
 * link. These tests lock that routing contract: a path that is not an API
 * route (for example `/svg`, which was reported as an HTTP 500 in
 * production) must return the SPA shell document - never a 404 and never a
 * server error.
 */
class SpaShellRoutingTest extends TestCase
{
    private string $shellPath;

    private bool $shellCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shellPath = public_path('build/index.html');

        // The Vite build is a build artifact and may be absent in a
        // backend-only checkout; a minimal stand-in keeps the routing test
        // deterministic without depending on the frontend build.
        if (! is_file($this->shellPath)) {
            @mkdir(dirname($this->shellPath), 0777, true);
            file_put_contents(
                $this->shellPath,
                '<!doctype html><html lang="fa" dir="rtl"><head>'
                .'<script type="module" src="/build/assets/index-test.js"></script>'
                .'</head><body><div id="root"></div></body></html>'
            );
            $this->shellCreated = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->shellCreated && is_file($this->shellPath)) {
            @unlink($this->shellPath);
        }

        parent::tearDown();
    }

    /**
     * @dataProvider spaPaths
     */
    public function test_non_api_paths_serve_the_spa_shell(string $path): void
    {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('/build/assets/', (string) $response->getContent());
        $this->assertStringNotContainsString('__ADMIN_BASE__', (string) $response->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function spaPaths(): array
    {
        return [
            'root' => ['/'],
            'svg' => ['/svg'],
            'deep link' => ['/consultants'],
            'nested deep link' => ['/consultants/some-slug'],
            'favicon-like path' => ['/favicon.svg'],
        ];
    }
}
