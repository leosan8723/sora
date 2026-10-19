<?php

namespace App\Http\Resources;

use App\Models\ConsultantProfile;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resolves localized display values for a consultant profile.
 *
 * English requests use the *_en fields when present and fall back to the
 * Persian originals otherwise (never inventing translations). Persian
 * requests always use the Persian originals.
 */
final class ResourceLocalization
{
    public string $name;

    public string $headline;

    public ?string $bio;

    public function __construct(ConsultantProfile|JsonResource $profile)
    {
        if ($profile instanceof JsonResource) {
            $profile = $profile->resource;
        }

        $english = app()->getLocale() === 'en';

        $this->name = $english
            ? ($profile->name_en ?: $profile->user->name)
            : $profile->user->name;

        $this->headline = $english
            ? ($profile->headline_en ?: (string) $profile->headline)
            : (string) $profile->headline;

        $this->bio = $english
            ? ($profile->bio_en ?: $profile->bio)
            : $profile->bio;
    }

    /** @return list<string> Profile fields still missing an English translation. */
    public static function missingEnglish(ConsultantProfile|JsonResource $input): array
    {
        $profile = $input instanceof JsonResource ? $input->resource : $input;

        $missing = [];

        if (($profile->name_en ?? '') === null || trim((string) $profile->name_en) === '') {
            $missing[] = 'name';
        }

        if ($profile->headline_en === null || trim($profile->headline_en) === '') {
            $missing[] = 'headline';
        }

        if ($profile->bio_en === null || trim($profile->bio_en) === '') {
            $missing[] = 'bio';
        }

        return $missing;
    }
}
