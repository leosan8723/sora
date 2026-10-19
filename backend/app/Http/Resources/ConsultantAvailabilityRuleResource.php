<?php

namespace App\Http\Resources;

use App\Models\AvailabilityRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner view of an availability rule. Unlike the public rule resource it
 * exposes the identifier and activation flag so the consultant can manage it.
 *
 * @mixin AvailabilityRule
 */
class ConsultantAvailabilityRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => (int) $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
