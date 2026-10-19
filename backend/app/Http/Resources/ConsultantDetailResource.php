<?php

namespace App\Http\Resources;

use App\Models\ConsultantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public detail representation of a consultant. Only discoverable profiles are
 * ever resolved, and only information a client needs to choose a consultant is
 * exposed.
 *
 * @mixin ConsultantProfile
 */
class ConsultantDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeTypes = $this->relationLoaded('consultationTypes')
            ? $this->consultationTypes->where('is_active', true)->sortBy('sort_order')->values()
            : collect();

        $activeRules = $this->relationLoaded('availabilityRules')
            ? $this->availabilityRules->where('is_active', true)->sortBy('start_time')->values()
            : collect();

        $localized = new ResourceLocalization($this);

        return [
            'id' => $this->id,
            'name' => $localized->name,
            'avatar_path' => $this->whenLoaded('user', fn () => $this->user->avatar_path),
            'headline' => $localized->headline,
            'bio' => $localized->bio,
            'years_experience' => $this->years_experience,
            'is_verified' => (bool) $this->is_verified,
            'specialties' => SpecialtyResource::collection($this->whenLoaded('specialties')),
            'consultation_types' => ConsultationTypeResource::collection($activeTypes),
            'starting_price_toman' => $activeTypes->min('price_toman'),
            'starting_price_usd_cents' => $activeTypes
                ->whereNotNull('price_usd_cents')
                ->min('price_usd_cents'),
            'avg_rating' => $this->whenNotNull(
                $this->approved_reviews_avg_rating !== null
                    ? round((float) $this->approved_reviews_avg_rating, 1)
                    : null
            ),
            'reviews_count' => $this->whenNotNull($this->approved_reviews_count),
            'availability' => $activeRules
                ->groupBy('weekday')
                ->map(fn ($rules, $weekday) => [
                    'weekday' => (int) $weekday,
                    'ranges' => AvailabilityRuleResource::collection($rules),
                ])
                ->sortBy('weekday')
                ->values(),
        ];
    }
}
