<?php

namespace App\Exceptions;

use Exception;

class GasEstimationFailedException extends Exception
{
    public function __construct(string $message = 'Failed to estimate gas for transaction')
    {
        parent::__construct($message);
    }
}