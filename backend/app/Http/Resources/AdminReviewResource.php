<?php

namespace App\Http\Resources;

use App\Models\ConsultantReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Moderation payload for admins: includes the review status and the context
 * (client, consultant, appointment) needed to judge a submission.
 *
 * @mixin ConsultantReview
 */
class AdminReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'status' => $this->status?->value,
            'client' => $this->whenLoaded('client', fn (): array => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'consultant' => $this->whenLoaded('consultantProfile', fn (): array => [
                'id' => $this->consultantProfile->id,
                'name' => $this->consultantProfile->user?->name,
            ]),
            'appointment' => $this->whenLoaded('appointment', fn (): array => [
                'id' => $this->appointment->id,
                'starts_at' => $this->appointment->starts_at?->toIso8601String(),
                'status' => $this->appointment->status?->value,
            ]),
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
