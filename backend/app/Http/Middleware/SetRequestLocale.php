<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Negotiate the API locale from the Accept-Language header so validation
 * errors and domain messages come back in the language the client displays.
 * Persian is the default (fa is the product's primary language); `en` is the
 * only other supported locale.
 */
class SetRequestLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        foreach ($request->getLanguages() as $candidate) {
            $locale = strtolower(substr($candidate, 0, 2));

            if (in_array($locale, ['fa', 'en'], true)) {
                App::setLocale($locale);

                break;
            }
        }

        return $next($request);
    }
}
