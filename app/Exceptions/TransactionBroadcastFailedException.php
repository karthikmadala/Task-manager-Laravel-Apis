<?php

namespace App\Exceptions;

use Exception;

class TransactionBroadcastFailedException extends Exception
{
    public function __construct(string $message = 'Failed to broadcast transaction to blockchain')
    {
        parent::__construct($message);
    }
}