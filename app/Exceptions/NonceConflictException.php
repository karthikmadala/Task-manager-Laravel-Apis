<?php

namespace App\Exceptions;

use Exception;

class NonceConflictException extends Exception
{
    public function __construct(string $message = 'Nonce conflict detected')
    {
        parent::__construct($message);
    }
}