<?php

namespace App\Enums;

enum ActivityType: string
{
    case Survey = 'SURVEY';
    case PlnConnection = 'PLN_CONNECTION';
    case Construction = 'CONSTRUCTION';
    case Bast = 'BAST';

    public function label(): string
    {
        return match ($this) {
            self::Survey => 'Survey',
            self::PlnConnection => 'PLN Connection',
            self::Construction => 'Construction',
            self::Bast => 'BAST',
        };
    }
}
