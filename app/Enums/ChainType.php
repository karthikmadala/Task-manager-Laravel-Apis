<?php

namespace App\Enums;

enum ChainType: string
{
    case ETH = 'eth';
    case BNB = 'bnb';
    case POLYGON = 'polygon';
    case BTC = 'btc';

    public function label(): string
    {
        return match ($this) {
            self::ETH => 'Ethereum',
            self::BNB => 'BNB Chain',
            self::POLYGON => 'Polygon',
            self::BTC => 'Bitcoin',
        };
    }

    public function isEvm(): bool
    {
        return match ($this) {
            self::ETH, self::BNB, self::POLYGON => true,
            self::BTC => false,
        };
    }

    public function isReadOnly(): bool
    {
        return $this === self::BTC;
    }

    public function nativeSymbol(): string
    {
        return match ($this) {
            self::ETH => 'ETH',
            self::BNB => 'BNB',
            self::POLYGON => 'MATIC',
            self::BTC => 'BTC',
        };
    }

    /** Regex pattern for address validation */
    public function addressPattern(): string
    {
        return match ($this) {
            self::ETH, self::BNB, self::POLYGON => '/^0x[0-9a-fA-F]{40}$/',
            self::BTC => '/^(1|3|bc1)[a-zA-HJ-NP-Z0-9]{25,62}$/',
        };
    }
}
