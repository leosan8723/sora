<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin()
            || $this->isClientOwner($user, $appointment)
            || $this->isConsultantOwner($user, $appointment);
    }

    /**
     * A participant may cancel an appointment only while it still holds a slot.
     * Consultants may cancel at any time before the start; clients are subject
     * to the cancellation lead time enforced in the booking service.
     */
    public function cancel(User $user, Appointment $appointment): bool
    {
        if (! $appointment->status->isActive()) {
            return false;
        }

        return $this->isClientOwner($user, $appointment)
            || $this->isConsultantOwner($user, $appointment);
    }

    /**
     * Only the client who booked may move the appointment to a new slot.
     */
    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $appointment->status->isActive() && $this->isClientOwner($user, $appointment);
    }

    /**
     * Consultants may confirm or complete their own appointments while the
     * appointment is still active. Attempting to confirm an already-confirmed
     * (or complete a pending) appointment is rejected by the service's state
     * machine, not here.
     */
    public function updateStatus(User $user, Appointment $appointment): bool
    {
        return $appointment->status->isActive()
            && ($user->isAdmin() || $this->isConsultantOwner($user, $appointment));
    }

    private function isClientOwner(User $user, Appointment $appointment): bool
    {
        return $user->isClient() && $appointment->client_id === $user->id;
    }

    private function isConsultantOwner(User $user, Appointment $appointment): bool
    {
        return $user->isConsultant()
            && $appointment->consultantProfile?->user_id === $user->id;
    }
}
