<?php

namespace App\Http\Controllers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Throwable;

/**
 * Serves the admin panel shell document at the configured private path.
 *
 * The path is validated server-side against the ADMIN_PATH environment
 * variable and is never included in any frontend bundle, sitemap, or public
 * response. The shell injects the path into the SPA bootstrap document via
 * window.__ADMIN_BASE__; the SPA treats it purely as navigation and relies
 * on API-side authentication + role authorization for security.
 */
class AdminPanelController extends Controller
{
    public function show(Request $request)
    {
        $configured = (string) config('sora.admin_path');
        $provided = (string) $request->route('panel');

        // Fail safe: no valid configuration means no shell.
        if ($configured === '' || ! hash_equals($configured, $provided)) {
            abort(404);
        }

        // Non-obvious error responses must not reveal that a protected area
        // exists, so every invalid attempt gets an identical 404.
        $shell = $this->resolveAssets();

        if ($shell === null) {
            // No built assets available and no dev server configured.
            // In production this is a deployment error; in local development
            // the Vite dev server is expected to be running.
            abort(500);
        }

        return response()
            ->view('admin-panel', [
                'adminBase' => '/'.$provided,
                'scriptUrl' => $shell['scriptUrl'],
                'cssUrls' => $shell['cssUrls'],
                'dev' => $shell['dev'] ?? null,
            ])
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Resolves the frontend entry assets.
     *
     * Production: reads the Vite manifest produced by the frontend build into
     * backend/public/build. Development: falls back to the Vite dev server.
     *
     * @return array{scriptUrl: string, cssUrls: list<string>}|null
     */
    private function resolveAssets(): ?array
    {
        // Vite writes .vite/manifest.json; rolldown-vite builds keep the
        // classic manifest.json path. Support both.
        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath) && is_file(public_path('build/.vite/manifest.json'))) {
            $manifestPath = public_path('build/.vite/manifest.json');
        }

        if (is_file($manifestPath)) {
            try {
                $manifest = json_decode((new Filesystem)->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return null;
            }

            $entry = $manifest['index.html'] ?? null;

            if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
                return null;
            }

            $cssUrls = [];

            foreach ((array) ($entry['css'] ?? []) as $cssFile) {
                if (is_string($cssFile)) {
                    $cssUrls[] = '/build/'.$cssFile;
                }
            }

            return [
                'scriptUrl' => '/build/'.$entry['file'],
                'cssUrls' => $cssUrls,
            ];
        }

        $devUrl = rtrim((string) config('sora.vite_dev_url'), '/');

        if ($devUrl === '') {
            return null;
        }

        return [
            'scriptUrl' => $devUrl.'/src/main.tsx',
            'cssUrls' => [],
            // Additional hints consumed by the blade view:
            'dev' => [
                'client' => $devUrl.'/@vite/client',
                'reactRefresh' => $devUrl.'/@react-refresh',
            ],
        ];
    }
}
