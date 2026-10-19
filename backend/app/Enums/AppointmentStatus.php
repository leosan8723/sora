<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTransitionsFrom($this), true);
    }

    /**
     * @return list<self>
     */
    public static function allowedTransitionsFrom(self $from): array
    {
        return match ($from) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }
}
