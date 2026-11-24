<?php

namespace Ongudidan\PaymentsEngine\Exceptions;

use Exception;

class UnbalancedJournalException extends Exception
{
    public function __construct(string $message = 'Total debits must strictly equal total credits in general ledger entries.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
