<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(
        string $required,
        string $available,
        string $symbol = 'ETH'
    ) {
        parent::__construct(
            "Insufficient balance for transaction. Required: {$required} {$symbol}, Available: {$available} {$symbol}"
        );
    }
}