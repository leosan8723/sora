<?php

namespace App\Http\Resources;

use App\Models\ConsultantReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public review payload. Reviewer identity is reduced to a display name
 * (given name + surname initial); no ids, emails, or appointment details are
 * exposed, and only approved reviews are listed by the controller.
 *
 * @mixin ConsultantReview
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwn = $this->client_id === $request->user()?->id;

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'author' => $this->whenLoaded('client', fn (): string => $this->clientDisplayName()),
            'is_verified' => true,
            'is_own' => $isOwn,
            // Only the author learns why their review is not public yet.
            'status' => $isOwn ? $this->status?->value : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
