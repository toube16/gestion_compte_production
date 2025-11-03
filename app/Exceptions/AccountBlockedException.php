<?php

namespace App\Exceptions;

use Exception;

class AccountBlockedException extends Exception
{
    public function __construct(string $message = "Le compte est bloqué", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}