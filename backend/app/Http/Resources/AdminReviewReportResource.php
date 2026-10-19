<?php

namespace App\Http\Resources;

use App\Models\ReviewReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Triage payload for admins: the report plus enough review context (author,
 * consultant, review text) to decide action without leaving the page.
 *
 * @mixin ReviewReport
 */
class AdminReviewReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'review' => $this->whenLoaded('review', fn (): array => [
                'id' => $this->review->id,
                'rating' => $this->review->rating,
                'body' => $this->review->body,
                'status' => $this->review->status?->value,
                'client' => $this->review->client ? [
                    'id' => $this->review->client->id,
                    'name' => $this->review->client->name,
                ] : null,
                'consultant' => $this->review->consultantProfile ? [
                    'id' => $this->review->consultantProfile->id,
                    'name' => $this->review->consultantProfile->user?->name,
                ] : null,
            ]),
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'email' => $this->reporter->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
