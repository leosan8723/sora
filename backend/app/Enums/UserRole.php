<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Consultant = 'consultant';
    case Admin = 'admin';

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
