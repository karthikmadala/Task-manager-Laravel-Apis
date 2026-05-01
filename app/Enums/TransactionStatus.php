<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted',
            self::CONFIRMED => 'Confirmed',
            self::FAILED => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::CONFIRMED, self::FAILED => true,
            default => false,
        };
    }
}
