<?php

namespace App\Http\Resources;

use App\Models\AvailabilityRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public, read-only view of an availability rule.
 *
 * @mixin AvailabilityRule
 */
class AvailabilityRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'weekday' => (int) $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
