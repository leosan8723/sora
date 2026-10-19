<?php

namespace App\Policies;

use App\Models\AvailabilityRule;
use App\Models\User;

class AvailabilityRulePolicy
{
    public function update(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $this->owns($user, $availabilityRule);
    }

    public function delete(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $this->owns($user, $availabilityRule);
    }

    /**
     * An availability rule may only be managed by the consultant who owns the
     * parent profile. Admin moderation is added with the admin phase.
     */
    private function owns(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $user->isConsultant()
            && $availabilityRule->consultantProfile?->user_id === $user->id;
    }
}
