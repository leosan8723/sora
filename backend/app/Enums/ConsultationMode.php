<?php

namespace App\Enums;

enum ConsultationMode: string
{
    case Online = 'online';
    case InPerson = 'in_person';

    /**
     * Raw values, useful for validation rules driven by config/settings.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
