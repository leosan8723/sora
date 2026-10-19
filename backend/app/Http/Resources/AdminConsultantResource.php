<?php

namespace App\Http\Resources;

use App\Models\ConsultantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Administrative view of a consultant profile with operational counters.
 *
 * @mixin ConsultantProfile
 */
class AdminConsultantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'is_active' => $this->user->is_active,
            ]),
            'headline' => $this->headline,
            'bio' => $this->bio,
            'name_en' => $this->name_en,
            'headline_en' => $this->headline_en,
            'bio_en' => $this->bio_en,
            'translation_missing_en' => ResourceLocalization::missingEnglish($this),
            'years_experience' => $this->years_experience,
            'is_verified' => (bool) $this->is_verified,
            'is_active' => (bool) $this->is_active,
            'specialties' => SpecialtyResource::collection($this->whenLoaded('specialties')),
            'consultation_types_count' => $this->whenCounted('consultationTypes'),
            'appointments_count' => $this->whenCounted('appointments'),
        ];
    }
}
