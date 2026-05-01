<?php

namespace App\Enums;

enum WalletType: string
{
    case EXTERNAL = 'external';
    case METAMASK = 'metamask';

    public function label(): string
    {
        return match ($this) {
            self::EXTERNAL => 'External Wallet',
            self::METAMASK => 'MetaMask',
        };
    }
}
