<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Participant view of an appointment. Times are exposed in UTC (the storage
 * format returned as ISO-8601) plus a Tehran-local string for display.
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = (string) config('booking.timezone', 'Asia/Tehran');
        $user = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'starts_at_tehran' => $this->starts_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'ends_at_tehran' => $this->ends_at->setTimezone($timezone)->format('Y-m-d H:i'),
            'duration_minutes' => $this->starts_at->diffInMinutes($this->ends_at),
            'price_toman' => (int) $this->price_toman,
            'price_usd_cents' => $this->price_usd_cents === null ? null : (int) $this->price_usd_cents,
            'mode' => $this->mode?->value,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'consultant' => $this->whenLoaded('consultantProfile', fn (): array => [
                'id' => $this->consultantProfile->id,
                'name' => (new ResourceLocalization($this->consultantProfile))->name,
                'headline' => $this->consultantProfile->headline,
                'avatar_path' => $this->consultantProfile->user?->avatar_path,
            ]),
            'consultation_type' => $this->whenLoaded('consultationType', fn (): array => [
                'id' => $this->consultationType->id,
                'title' => $this->consultationType->title,
                'duration_minutes' => $this->consultationType->duration_minutes,
            ]),
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'can' => [
                'cancel' => $user !== null && Gate::forUser($user)->allows('cancel', $this->resource),
                'reschedule' => $user !== null && Gate::forUser($user)->allows('reschedule', $this->resource),
                'confirm' => $user !== null
                    && Gate::forUser($user)->allows('updateStatus', $this->resource)
                    && $this->status->value === 'pending',
                'complete' => $user !== null
                    && Gate::forUser($user)->allows('updateStatus', $this->resource)
                    && $this->status->value === 'confirmed',
            ],
        ];
    }
}
