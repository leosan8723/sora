<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Presentation timezone used to report human-readable local time.
     * Storage remains UTC (see ARCHITECTURE.md, Timezone Policy).
     */
    private const DISPLAY_TIMEZONE = 'Asia/Tehran';

    public function __invoke(Request $request): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $database = 'unavailable';
        }

        $now = now()->utc();

        // Readiness semantics: a database that cannot be reached means the
        // application cannot serve any data (the session store and every
        // API query live in the database), so the probe answers 503 and
        // reports the degraded state. The JSON body shape is unchanged, so
        // existing consumers keep working.
        $healthy = $database === 'ok';

        return response()->json([
            'data' => [
                'status' => $healthy ? 'ok' : 'unhealthy',
                'application' => config('app.name'),
                'environment' => config('app.env'),
                'time_utc' => $now->toIso8601String(),
                'time_tehran' => $now->timezone(self::DISPLAY_TIMEZONE)->toIso8601String(),
                'timezone' => config('app.timezone'),
                'display_timezone' => self::DISPLAY_TIMEZONE,
                'database' => $database,
            ],
        ], $healthy ? 200 : 503);
    }
}
