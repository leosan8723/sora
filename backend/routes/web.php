<?php

use App\Http\Controllers\AdminPanelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The public SPA is the static Vite build in public/build. Laravel serves
 * the SPA shell document for `/` and for every client-side deep link, while
 * the web server (Apache on shared hosting / in the production container,
 * FrankenPHP, nginx, ...) serves the built assets under /build directly.
 * This keeps one production origin for the API, the SPA, and the private
 * admin shell on every deployment target.
 *
 * A single catch-all handles both:
 *   - the private admin shell, when the requested path equals the configured
 *     ADMIN_PATH exactly (runtime hash_equals; AdminPanelController remains
 *     the authoritative server-side check), otherwise
 *   - the SPA shell document, which is served for every other GET path.
 *
 * Every unknown GET path therefore receives the same public SPA response —
 * probing the private path reveals nothing.
 *
 * Excluded from the catch-all:
 *   - `api/*` -> JSON API (routes/api.php, prefix api/v1)
 *   - `up`    -> Laravel health probe (registered by the framework)
 */

$spaShell = function () {
    $shell = public_path('build/index.html');

    abort_if(! is_file($shell), 500, 'SPA build is missing.');

    // Return the shell document as a regular response: identical output for
    // real web servers (Apache/nginx/FrankenPHP), while feature tests can
    // still assert against the body (BinaryFileResponse exposes no content
    // through getContent() in a test kernel).
    $content = file_get_contents($shell);
    abort_if($content === false || $content === '', 500, 'SPA build is missing.');

    return response($content, 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
};

Route::get('/', $spaShell);

Route::get('/{panel}', function (Request $request) use ($spaShell) {
    $configured = (string) config('sora.admin_path');
    $provided = (string) $request->route('panel');

    if ($configured !== '') {
        if (hash_equals($configured, $provided)) {
            return app(AdminPanelController::class)->show($request);
        }
    }

    return $spaShell();
})
    ->where('panel', '^(?!api(?:/|$))(?!up$).+');

// Unknown POST/PATCH/DELETE paths intentionally fall through to a plain 404
// (the SPA navigates via GET only).
