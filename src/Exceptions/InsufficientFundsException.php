<?php

namespace Ongudidan\PaymentsEngine\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(string $message = 'Insufficient funds for the requested transaction.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
