<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Administrative view of a user account. Exposes fields that must never
 * appear on public/participant endpoints; the route is admin-only.
 *
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601ZuluString(),
            'password_set' => $this->password_set_at !== null,
            'password_set_at' => $this->password_set_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'consultant' => $this->whenLoaded('consultantProfile', fn (): ?array => $this->consultantProfile === null ? null : [
                'id' => $this->consultantProfile->id,
                'headline' => $this->consultantProfile->headline,
                'is_verified' => (bool) $this->consultantProfile->is_verified,
                'is_active' => (bool) $this->consultantProfile->is_active,
            ]),
        ];
    }
}
