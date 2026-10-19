<?php

namespace App\Support;

use App\Enums\ConsultationMode;
use App\Models\SiteSetting;

/**
 * Effective list of consultation modes consultants may offer. Admins narrow
 * the configured list through site settings; the fallback is the config
 * default, so the platform always has at least one usable mode.
 */
final class ConsultationModes
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $configured = SiteSetting::get('supported_modes');

        if (is_array($configured)) {
            $valid = array_values(array_intersect(
                array_map('strval', $configured),
                ConsultationMode::values(),
            ));

            if ($valid !== []) {
                return $valid;
            }
        }

        $defaults = config('booking.supported_modes');

        return is_array($defaults) && $defaults !== []
            ? array_map('strval', $defaults)
            : ConsultationMode::values();
    }
}
