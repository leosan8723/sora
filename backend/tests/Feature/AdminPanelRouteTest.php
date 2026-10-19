<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPanelRouteTest extends TestCase
{
    private const TEST_ADMIN_PATH = 'sora-manage-test-123';

    /**
     * The private admin shell is verified at REQUEST time against the runtime
     * ADMIN_PATH configuration (hash_equals inside AdminPanelController); the
     * SPA catch-all route delegates to it for the exact configured path only.
     */
    public function test_admin_shell_serves_at_configured_private_path(): void
    {
        config()->set('sora.admin_path', self::TEST_ADMIN_PATH);

        $response = $this->get('/'.self::TEST_ADMIN_PATH);

        $response->assertOk();
        $response->assertSee('__ADMIN_BASE__ = "/'.self::TEST_ADMIN_PATH.'"', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * Unknown GET paths serve the public SPA, indistinguishably from any
     * other deep link — so probing for the private path reveals nothing.
     * None of them may ever return the admin shell document.
     */
    public function test_unknown_paths_serve_the_spa_and_never_the_shell(): void
    {
        config()->set('sora.admin_path', self::TEST_ADMIN_PATH);

        foreach ([
            self::TEST_ADMIN_PATH.'1',
            self::TEST_ADMIN_PATH.'-x',
            self::TEST_ADMIN_PATH.'-longer-variant',
            self::TEST_ADMIN_PATH.'/extra',
            'admin',
            'consultants',
            'contact-us',
        ] as $path) {
            $response = $this->get('/'.$path);

            $response->assertOk();
            $this->assertStringNotContainsString(
                '__ADMIN_BASE__',
                (string) $response->getContent(),
                "Route '/{$path}' must not serve the admin shell."
            );
        }
    }

    /**
     * An empty/missing ADMIN_PATH disables the shell entirely: every GET
     * path — including the would-be private path — serves the public SPA and
     * never the shell document (all admin API endpoints remain
     * authentication + role protected regardless).
     */
    public function test_shell_is_disabled_when_not_configured(): void
    {
        foreach ([null, ''] as $unconfigured) {
            config()->set('sora.admin_path', $unconfigured);

            $response = $this->get('/'.self::TEST_ADMIN_PATH);

            $response->assertOk();
            $this->assertStringNotContainsString('__ADMIN_BASE__', (string) $response->getContent());
        }
    }

    /**
     * The shell is reachable only for the exact configured value (length is
     * irrelevant — the value itself is the security); lookalikes of admin-
     * shell-like shape are ordinary SPA deep links.
     */
    public function test_only_exact_configured_value_serves_the_shell(): void
    {
        config()->set('sora.admin_path', 'short12');

        $exact = $this->get('/short12');
        $exact->assertOk();
        $exact->assertSee('__ADMIN_BASE__ = "/short12"', false);

        $lookalike = $this->get('/short13');
        $lookalike->assertOk();
        $this->assertStringNotContainsString('__ADMIN_BASE__', (string) $lookalike->getContent());
    }
}
