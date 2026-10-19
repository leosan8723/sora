<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Every production target terminates HTTPS behind a proxy/load
        // balancer (shared hosting control panel, Railway). Trust forwarded
        // headers so generated URLs, redirects and secure cookies use https.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // A deactivated account must lose API access immediately, even on
        // plain authentication-guarded routes. Locale negotiation happens
        // right after, so validation errors match the client's language.
        $middleware->api(prepend: [EnsureUserIsActive::class, SetRequestLocale::class]);

        // Unauthenticated API requests must receive 401 JSON. Without this,
        // requests that omit `Accept: application/json` fall through to the
        // session login redirect and explode with "Route [login] not defined".
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return Route::has('login') ? route('login') : '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = [];

            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = [
                        'code' => 'validation_error',
                        'field' => $field,
                        'message' => $message,
                    ];
                }
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $errors,
            ], 422);
        });
    })->create();
