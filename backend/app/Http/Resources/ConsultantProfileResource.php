<?php

namespace App\Http\Resources;

use App\Models\ConsultantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Self-management representation of a consultant profile (owner view).
 *
 * @mixin ConsultantProfile
 */
class ConsultantProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'years_experience' => $this->years_experience,
            'is_verified' => (bool) $this->is_verified,
            'is_active' => (bool) $this->is_active,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'specialties' => SpecialtyResource::collection($this->whenLoaded('specialties')),
            'consultation_types' => ConsultantConsultationTypeResource::collection($this->whenLoaded('consultationTypes')),
            'availability_rules' => AvailabilityRuleResource::collection($this->whenLoaded('availabilityRules')),
        ];
    }
}
