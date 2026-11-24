<?php

namespace Ongudidan\PaymentsEngine\Exceptions;

use Exception;

class DuplicateTransactionException extends Exception
{
    public function __construct(string $message = 'Duplicate transaction detected; operation rejected to prevent double posting.', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
