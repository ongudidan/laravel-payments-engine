<?php

namespace Ongudidan\PaymentsEngine\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;

class MpesaTransactionReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public MpesaTransaction $transaction) {}
}
