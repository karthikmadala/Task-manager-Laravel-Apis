<?php

namespace App\Exceptions;

use Exception;

class ContractCallFailedException extends Exception
{
    public function __construct(string $message = 'Contract call failed')
    {
        parent::__construct($message);
    }
}