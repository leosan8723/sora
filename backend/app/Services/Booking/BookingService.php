<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All state changes to appointments flow through here so availability,
 * status-transition, and concurrency rules live in one place
 * (ARCHITECTURE.md §9).
 */
class BookingService
{
    public function __construct(private readonly SlotGenerator $slots) {}

    /**
     * Book a slot for a client. The consultant profile row is locked for the
     * duration of the transaction so overlapping candidate slots serialize per
     * consultant; the unique `active_slot_key` index is the final guard.
     */
    public function book(
        User $client,
        ConsultationType $type,
        CarbonImmutable $startsAtUtc,
        ?string $notes = null,
    ): Appointment {
        $startsAtUtc = $startsAtUtc->utc();

        return DB::transaction(function () use ($client, $type, $startsAtUtc, $notes): Appointment {
            $profile = $this->lockProfile((int) $type->consultant_profile_id);

            $this->assertSlotBookable($profile, $type, $startsAtUtc, null);

            $this->assertBookingCurrency($type, $startsAtUtc);
            $this->assertEmailVerified($client);

            $appointment = new Appointment([
                'client_id' => $client->id,
                'consultant_profile_id' => $profile->id,
                'consultation_type_id' => $type->id,
                'starts_at' => $startsAtUtc,
                'ends_at' => $startsAtUtc->addMinutes($this->duration($type)),
                'status' => AppointmentStatus::Pending,
                'price_toman' => (int) $type->price_toman,
                'price_usd_cents' => $type->price_usd_cents === null ? null : (int) $type->price_usd_cents,
                'mode' => $type->mode,
                'notes' => $notes,
            ]);

            $this->persist($appointment);

            return $appointment;
        }, 5);
    }

    /**
     * Move an appointment to a new slot. The client-facing lead time and the
     * same availability rules as booking apply.
     */
    public function reschedule(
        User $user,
        Appointment $appointment,
        CarbonImmutable $startsAtUtc,
    ): Appointment {
        $startsAtUtc = $startsAtUtc->utc();

        return DB::transaction(function () use ($user, $appointment, $startsAtUtc): Appointment {
            $appointment = $this->lockAppointment((int) $appointment->id);

            $profile = $this->lockProfile((int) $appointment->consultant_profile_id);
            $type = $appointment->consultationType()->firstOrFail();

            $this->assertActive($appointment);
            $this->assertWithinModificationWindow($user, $appointment, 'reschedule_lead_hours');
            $this->assertSlotBookable($profile, $type, $startsAtUtc, $appointment->id);

            $appointment->starts_at = $startsAtUtc;
            $appointment->ends_at = $startsAtUtc->addMinutes($this->duration($type));

            $this->persist($appointment);

            return $appointment;
        }, 5);
    }

    /**
     * Cancel an appointment. Clients are bound by the cancellation lead time;
     * consultants may cancel up to the start time.
     */
    public function cancel(User $user, Appointment $appointment, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($user, $appointment, $reason): Appointment {
            $appointment = $this->lockAppointment((int) $appointment->id);

            $this->assertActive($appointment);
            $this->assertWithinModificationWindow($user, $appointment, 'cancellation_lead_hours');

            $appointment->status = AppointmentStatus::Cancelled;
            $appointment->cancelled_at = now();
            $appointment->cancelled_by_id = $user->id;
            $appointment->cancellation_reason = $reason;

            $this->persist($appointment);

            return $appointment;
        }, 5);
    }

    /**
     * Apply a consultant-driven status change (confirm or complete). The
     * appointment row is locked so the transition cannot interleave with a
     * concurrent cancel/reschedule, and the state machine in
     * {@see AppointmentStatus::canTransitionTo()} rejects invalid moves.
     */
    public function transitionStatus(Appointment $appointment, AppointmentStatus $target): Appointment
    {
        return DB::transaction(function () use ($appointment, $target): Appointment {
            $appointment = $this->lockAppointment((int) $appointment->id);

            $this->assertActive($appointment);

            if (! $appointment->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => __('booking.status_transition'),
                ]);
            }

            $appointment->status = $target;

            $this->persist($appointment);

            return $appointment;
        }, 5);
    }

    /**
     * English-locale bookings require a real USD price on the session type:
     * without it the advertised cost cannot be displayed honestly in USD.
     * The toman price is always mandatory; no exchange rate is ever invented
     * here. PHP requests (fa) are unaffected.
     */
    /**
     * Registration must have completed address verification before a booking
     * is accepted. Factory-made users are verified by default, so this only
     * gates genuinely fresh accounts.
     */
    private function assertEmailVerified(User $client): void
    {
        if ($client->email_verified_at === null) {
            throw ValidationException::withMessages([
                'email' => __('booking.email_unverified'),
            ]);
        }
    }

    private function assertBookingCurrency(ConsultationType $type, CarbonImmutable $startsAtUtc): void
    {
        if (app()->getLocale() === 'en' && $type->price_usd_cents === null) {
            throw ValidationException::withMessages([
                'consultation_type_id' => __('booking.usd_price_required'),
            ]);
        }
    }

    /**
     * A valid availability slot that is not already held by an active
     * appointment. "Not on the availability grid" is a 422; "just taken" is a
     * 409 (the race described in ARCHITECTURE.md §9).
     */
    private function assertSlotBookable(
        ConsultantProfile $profile,
        ConsultationType $type,
        CarbonImmutable $startsAtUtc,
        ?int $ignoreAppointmentId,
    ): void {
        if (! $profile->is_active || ! $profile->is_verified) {
            throw ValidationException::withMessages([
                'consultation_type_id' => __('booking.consultant_unavailable'),
            ]);
        }

        if ($type->consultant_profile_id !== $profile->id || ! $type->is_active) {
            throw ValidationException::withMessages([
                'consultation_type_id' => __('booking.type_unavailable'),
            ]);
        }

        if ($startsAtUtc->lessThan($this->earliestBookable())) {
            throw ValidationException::withMessages([
                'starts_at' => __('booking.slot_past'),
            ]);
        }

        if (! $this->slots->isWithinAvailability($profile, $type, $startsAtUtc)) {
            throw ValidationException::withMessages([
                'starts_at' => __('booking.slot_not_available'),
            ]);
        }

        $endsAt = $startsAtUtc->addMinutes($this->duration($type));

        if ($this->slots->overlappingAppointment($profile, $startsAtUtc, $endsAt, $ignoreAppointmentId) !== null) {
            throw new BookingConflictException;
        }
    }

    private function assertActive(Appointment $appointment): void
    {
        if (! $appointment->status->isActive()) {
            throw ValidationException::withMessages([
                'status' => __('booking.not_active'),
            ]);
        }
    }

    private function assertWithinModificationWindow(User $user, Appointment $appointment, string $configKey): void
    {
        if (! $user->isClient()) {
            return;
        }

        $hours = (int) config("booking.{$configKey}", 0);
        $cutoff = $this->now()->addHours($hours);

        if ($appointment->starts_at->lessThan($cutoff)) {
            throw ValidationException::withMessages([
                'starts_at' => __('booking.lead_time_passed'),
            ]);
        }
    }

    private function persist(Appointment $appointment): void
    {
        try {
            $appointment->save();
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new BookingConflictException;
            }

            throw $exception;
        }
    }

    private function lockProfile(int $profileId): ConsultantProfile
    {
        return ConsultantProfile::query()
            ->whereKey($profileId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockAppointment(int $appointmentId): Appointment
    {
        return Appointment::query()
            ->whereKey($appointmentId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function duration(ConsultationType $type): int
    {
        return max(1, (int) $type->duration_minutes);
    }

    private function earliestBookable(): CarbonImmutable
    {
        return $this->now()->addMinutes((int) config('booking.minimum_lead_minutes', 0));
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->slots->timezone());
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            || ($exception->errorInfo[0] ?? null) === '23000';
    }
}
