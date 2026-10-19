<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'consultant_profile_id',
        'consultation_type_id',
        'mode',
        'starts_at',
        'ends_at',
        'status',
        'price_toman',
        'price_usd_cents',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'mode' => ConsultationMode::class,
            'price_toman' => 'integer',
            'price_usd_cents' => 'integer',
        ];
    }

    /**
     * The active-slot key is derived, never mass assigned. It is the single
     * value protected by the unique index that prevents two active
     * appointments from holding the same slot (ARCHITECTURE.md, §9).
     */
    protected static function booted(): void
    {
        static::saving(function (self $appointment): void {
            $appointment->active_slot_key = $appointment->holdsActiveSlot()
                ? $appointment->generateSlotKey()
                : null;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function consultantProfile(): BelongsTo
    {
        return $this->belongsTo(ConsultantProfile::class);
    }

    public function consultationType(): BelongsTo
    {
        return $this->belongsTo(ConsultationType::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /**
     * The review left for this appointment (at most one).
     */
    public function review(): HasOne
    {
        return $this->hasOne(ConsultantReview::class);
    }

    /**
     * Restrict a query to the appointments the given user is a participant in:
     * their own bookings as a client, or bookings made with their consultant
     * profile. Admins see everything.
     */
    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        if ($user->isClient()) {
            return $query->where('client_id', $user->id);
        }

        if ($user->isConsultant()) {
            return $query->whereHas(
                'consultantProfile',
                fn (Builder $profile): Builder => $profile->where('user_id', $user->id),
            );
        }

        return $query;
    }

    /**
     * Appointments that still lie ahead: active statuses starting at or after
     * the given instant, soonest first.
     */
    public function scopeUpcoming(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->whereIn('status', $this->activeStatusValues())
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at');
    }

    /**
     * Appointments in the past: anything terminal, or anything whose start time
     * has already passed, most recent first.
     */
    public function scopePast(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereIn('status', [
                    AppointmentStatus::Completed->value,
                    AppointmentStatus::Cancelled->value,
                ])->orWhere('starts_at', '<', $now);
            })
            ->orderByDesc('starts_at');
    }

    /**
     * @return list<string>
     */
    private function activeStatusValues(): array
    {
        return [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
        ];
    }

    public function holdsActiveSlot(): bool
    {
        if ($this->consultant_profile_id === null || $this->starts_at === null) {
            return false;
        }

        $status = $this->status instanceof AppointmentStatus
            ? $this->status
            : AppointmentStatus::tryFrom((string) $this->status);

        return $status !== null && $status->isActive();
    }

    protected function generateSlotKey(): string
    {
        return sprintf(
            '%d|%s',
            $this->consultant_profile_id,
            $this->starts_at->copy()->utc()->format('Y-m-d H:i:s'),
        );
    }
}
