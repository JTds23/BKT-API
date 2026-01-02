<?php

namespace App\Enums;

enum BookingRequestStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::CANCELLED => 'Cancelled',
        };
    }
}
