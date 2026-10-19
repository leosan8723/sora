<?php

namespace App\Policies;

use App\Models\ConsultationType;
use App\Models\User;

class ConsultationTypePolicy
{
    public function view(User $user, ConsultationType $consultationType): bool
    {
        return $this->owns($user, $consultationType);
    }

    public function update(User $user, ConsultationType $consultationType): bool
    {
        return $this->owns($user, $consultationType);
    }

    public function delete(User $user, ConsultationType $consultationType): bool
    {
        return $this->owns($user, $consultationType);
    }

    /**
     * A consultation type may only be managed by the consultant who owns the
     * parent profile. Admin moderation is added with the admin phase.
     */
    private function owns(User $user, ConsultationType $consultationType): bool
    {
        return $user->isConsultant()
            && $consultationType->consultantProfile?->user_id === $user->id;
    }
}
