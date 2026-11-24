<?php

namespace Ongudidan\PaymentsEngine\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ongudidan\PaymentsEngine\Models\CustomerWalletTransaction;

class CustomerWalletDebited
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomerWalletTransaction $walletTransaction) {}
}
