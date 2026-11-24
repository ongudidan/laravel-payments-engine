<?php

namespace Ongudidan\PaymentsEngine\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\PaymentsEngine\Models\PaymentAccountTransfer;

class PaymentAccountTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentAccountTransfer $transfer) {}
}
