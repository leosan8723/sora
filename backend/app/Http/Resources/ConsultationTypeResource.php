<?php

namespace App\Http\Resources;

use App\Models\ConsultationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a consultation type.
 *
 * @mixin ConsultationType
 */
class ConsultationTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'duration_minutes' => (int) $this->duration_minutes,
            'price_toman' => (int) $this->price_toman,
            'price_usd_cents' => $this->price_usd_cents === null ? null : (int) $this->price_usd_cents,
            'mode' => $this->mode?->value ?? 'in_person',
        ];
    }
}
