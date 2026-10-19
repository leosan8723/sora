<?php

namespace App\Http\Resources;

use App\Models\ConsultationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Administrative view of a consultation type. The admin can edit the
 * authoritative price and mode here; booked appointments keep their
 * snapshot and are never retroactively changed.
 *
 * @mixin ConsultationType
 */
class AdminConsultationTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consultant' => $this->whenLoaded('consultantProfile', fn (): array => [
                'id' => $this->consultantProfile->id,
                'name' => $this->consultantProfile->user?->name,
            ]),
            'title' => $this->title,
            'description' => $this->description,
            'duration_minutes' => (int) $this->duration_minutes,
            'price_toman' => (int) $this->price_toman,
            'price_usd_cents' => $this->price_usd_cents === null ? null : (int) $this->price_usd_cents,
            'mode' => $this->mode?->value ?? 'in_person',
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
