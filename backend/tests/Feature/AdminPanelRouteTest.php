<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPanelRouteTest extends TestCase
{
    public function test_admin_shell_serves_at_configured_private_path(): void
    {
        config()->set('sora.admin_path', 'sora-manage-test-123');

        $response = $this->get('/sora-manage-test-123');

        $response->assertOk();
        $response->assertSee('__ADMIN_BASE__ = "/sora-manage-test-123"', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_admin_shell_is_404_for_other_paths(): void
    {
        config()->set('sora.admin_path', 'sora-manage-test-123');

        $this->get('/sora-manage-test-124')->assertNotFound();
        $this->get('/sora-manage-test-123x')->assertNotFound();
        $this->get('/admin')->assertNotFound();
        $this->get('/sora-manage-test-123/extra')->assertNotFound();
    }

    public function test_admin_shell_fails_safe_when_not_configured(): void
    {
        config()->set('sora.admin_path', null);

        $this->get('/sora-manage-test-123')->assertNotFound();
        $this->get('/admin')->assertNotFound();

        config()->set('sora.admin_path', '');

        $this->get('/sora-manage-test-123')->assertNotFound();
    }

    public function test_configured_path_with_unexpected_length_is_404(): void
    {
        // The route constraint rejects segments shorter than 8 characters.
        config()->set('sora.admin_path', 'short12');

        $this->get('/short12')->assertNotFound();
    }
}
