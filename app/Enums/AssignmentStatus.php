<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Revision = 'REVISION';
    case Verified = 'VERIFIED';
    case Reported = 'REPORTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Revision => 'Revision',
            self::Verified => 'Verified',
            self::Reported => 'Reported',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Completed => 'blue',
            self::Revision => 'amber',
            self::Verified => 'green',
            self::Reported => 'purple',
        };
    }
}
