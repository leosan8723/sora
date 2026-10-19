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

        return response()->json([
            'data' => [
                'status' => 'ok',
                'application' => config('app.name'),
                'environment' => config('app.env'),
                'time_utc' => $now->toIso8601String(),
                'time_tehran' => $now->timezone(self::DISPLAY_TIMEZONE)->toIso8601String(),
                'timezone' => config('app.timezone'),
                'display_timezone' => self::DISPLAY_TIMEZONE,
                'database' => $database,
            ],
        ]);
    }
}
