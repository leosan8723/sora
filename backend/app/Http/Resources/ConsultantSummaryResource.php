<?php

namespace App\Http\Resources;

use App\Models\ConsultantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public list representation of a consultant. Private account details
 * (email, phone, activation/verification state of the account) are omitted.
 *
 * @mixin ConsultantProfile
 */
class ConsultantSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeTypes = $this->relationLoaded('consultationTypes')
            ? $this->consultationTypes->where('is_active', true)
            : null;

        // English display fields fall back to the Persian originals; the
        // Persian text is never presented to operators as a verified
        // translation (the admin panel flags missing English content).
        $localized = new ResourceLocalization($this);

        return [
            'id' => $this->id,
            'name' => $localized->name,
            'avatar_path' => $this->whenLoaded('user', fn () => $this->user->avatar_path),
            'headline' => $localized->headline,
            'years_experience' => $this->years_experience,
            'is_verified' => (bool) $this->is_verified,
            'specialties' => SpecialtyResource::collection($this->whenLoaded('specialties')),
            'starting_price_toman' => $activeTypes?->min('price_toman'),
            // Minimum USD fee among session types that actually carry one; no
            // exchange-rate conversion is invented when none is configured.
            'starting_price_usd_cents' => $activeTypes
                ?->whereNotNull('price_usd_cents')
                ->min('price_usd_cents'),
            'avg_rating' => $this->whenNotNull(
                $this->approved_reviews_avg_rating !== null
                    ? round((float) $this->approved_reviews_avg_rating, 1)
                    : null
            ),
            'reviews_count' => $this->whenNotNull($this->approved_reviews_count),
        ];
    }
}
