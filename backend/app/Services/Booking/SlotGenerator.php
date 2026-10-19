<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Derives bookable time slots from a consultant's weekly availability rules.
 *
 * Availability rules are authored as Tehran wall-clock `HH:MM` ranges, while
 * appointments are stored in UTC (ARCHITECTURE.md §9–§10). All conversions
 * happen here, at the boundary, using Carbon with an explicit timezone.
 */
class SlotGenerator
{
    public function timezone(): string
    {
        return (string) config('booking.timezone', 'Asia/Tehran');
    }

    /**
     * Candidate slot start times (UTC) for a consultation type on a single
     * Tehran calendar day, derived from rules only. Existing appointments and
     * the "already started" filter are applied by {@see availableStarts()}.
     *
     * Slots are aligned to the consultation type duration and must fit inside a
     * rule's range, so two slots of the same type never overlap.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function candidateStarts(
        ConsultantProfile $profile,
        ConsultationType $type,
        CarbonImmutable $dateTehran,
    ): Collection {
        $weekday = $dateTehran->dayOfWeek;
        $duration = max(1, (int) $type->duration_minutes);

        $rules = $this->rulesFor($profile)
            ->where('weekday', $weekday)
            ->where('is_active', true);

        $starts = [];

        foreach ($rules as $rule) {
            $rangeStart = $this->atTehranTime($dateTehran, (string) $rule->start_time);
            $rangeEnd = $this->atTehranTime($dateTehran, (string) $rule->end_time);

            if ($rangeEnd->lessThanOrEqualTo($rangeStart)) {
                continue;
            }

            for (
                $start = $rangeStart;
                $start->addMinutes($duration)->lessThanOrEqualTo($rangeEnd);
                $start = $start->addMinutes($duration)
            ) {
                $starts[$start->getTimestamp()] = $start->utc();
            }
        }

        ksort($starts);

        return collect(array_values($starts));
    }

    /**
     * Slot starts that are actually bookable: on the availability grid, not in
     * the past (respecting the minimum lead time), and not overlapping any
     * active appointment.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function availableStarts(
        ConsultantProfile $profile,
        ConsultationType $type,
        CarbonImmutable $dateTehran,
        ?CarbonImmutable $now = null,
    ): Collection {
        $earliest = ($now ?? CarbonImmutable::now($this->timezone()))
            ->addMinutes((int) config('booking.minimum_lead_minutes', 0));

        $candidates = $this->candidateStarts($profile, $type, $dateTehran);

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $busy = $this->activeAppointments(
            $profile,
            $dateTehran->startOfDay()->utc(),
            $dateTehran->endOfDay()->utc(),
        );

        return $candidates
            ->filter(function (CarbonImmutable $start) use ($earliest, $busy, $type): bool {
                if ($start->lessThan($earliest)) {
                    return false;
                }

                $end = $start->addMinutes(max(1, (int) $type->duration_minutes));

                return ! $this->overlapsAny($busy, $start, $end);
            })
            ->values();
    }

    /**
     * Whether a requested start time is a valid slot on the availability grid
     * (ignoring existing appointments). Used to distinguish "not offered"
     * (422) from "just taken" (409).
     */
    public function isWithinAvailability(
        ConsultantProfile $profile,
        ConsultationType $type,
        CarbonImmutable $startUtc,
    ): bool {
        $dateTehran = $startUtc->setTimezone($this->timezone());

        return $this->candidateStarts($profile, $type, $dateTehran)
            ->contains(fn (CarbonImmutable $slot): bool => $slot->equalTo($startUtc->utc()));
    }

    /**
     * An active appointment that would overlap the given UTC range, if any.
     */
    public function overlappingAppointment(
        ConsultantProfile $profile,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        ?int $ignoreAppointmentId = null,
    ): ?Appointment {
        return $profile->appointments()
            ->whereIn('status', $this->activeStatuses())
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->first();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function activeAppointments(
        ConsultantProfile $profile,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): Collection {
        return $profile->appointments()
            ->whereIn('status', $this->activeStatuses())
            ->where('starts_at', '<', $toUtc)
            ->where('ends_at', '>', $fromUtc)
            ->get();
    }

    /**
     * @param  Collection<int, Appointment>  $busy
     */
    private function overlapsAny(Collection $busy, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $busy->contains(
            fn (Appointment $appointment): bool => $appointment->starts_at->lessThan($end)
                && $appointment->ends_at->greaterThan($start)
        );
    }

    /**
     * @return Collection<int, AvailabilityRule>
     */
    private function rulesFor(ConsultantProfile $profile): Collection
    {
        return $profile->relationLoaded('availabilityRules')
            ? $profile->availabilityRules
            : $profile->availabilityRules()->get();
    }

    private function atTehranTime(CarbonImmutable $dateTehran, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $dateTehran->setTime($hour, $minute);
    }

    /**
     * @return list<string>
     */
    private function activeStatuses(): array
    {
        return [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
        ];
    }
}
