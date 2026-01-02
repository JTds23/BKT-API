<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case GENERATED = 'generated';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::GENERATED => 'Generated',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
        };
    }
}
