<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    // Shared
    case Pending = 'PENDING';
    case Drop = 'DROP';
    case Verified = 'VERIFIED';
    case Reported = 'REPORTED';

    // Survey-specific
    case Survey = 'SURVEY';
    case Document = 'DOCUMENT';

    // Construction-specific
    case Construction = 'CONSTRUCTION';
    case MachineOnsite = 'MACHINE_ONSITE';
    case Done = 'DONE';
    case Live = 'LIVE';

    // PLN-specific
    case Registration = 'REGISTRATION';
    case Billing = 'BILLING';
    case Connection = 'CONNECTION';
    case KwhDone = 'KWH_DONE';

    // BAST-specific
    case Completed = 'COMPLETED';
    case Revision = 'REVISION';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Drop => 'Drop',
            self::Verified => 'Verified',
            self::Reported => 'Reported',
            self::Survey => 'Survey',
            self::Document => 'Document',
            self::Construction => 'Construction',
            self::MachineOnsite => 'Machine Onsite',
            self::Done => 'Done',
            self::Live => 'Live',
            self::Registration => 'Registration',
            self::Billing => 'Billing',
            self::Connection => 'Connection',
            self::KwhDone => 'KWH Done',
            self::Completed => 'Completed',
            self::Revision => 'Revision',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Drop => 'red',
            self::Verified => 'green',
            self::Reported => 'purple',
            self::Survey => 'sky',
            self::Document => 'indigo',
            self::Construction => 'orange',
            self::MachineOnsite => 'amber',
            self::Done => 'lime',
            self::Live => 'emerald',
            self::Registration => 'teal',
            self::Billing => 'cyan',
            self::Connection => 'blue',
            self::KwhDone => 'violet',
            self::Completed => 'blue',
            self::Revision => 'amber',
        };
    }

    /** Returns statuses where the admin Verify button should be available. */
    public static function verifiableStatuses(): array
    {
        return [self::Document, self::Completed, self::Live, self::KwhDone];
    }

    /** Returns statuses that are set by admin and must not be auto-overwritten. */
    public static function adminLocked(): array
    {
        return [self::Verified, self::Reported, self::Drop, self::Revision];
    }
}
