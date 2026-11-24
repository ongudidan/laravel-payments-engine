<?php

namespace Ongudidan\PaymentsEngine\Enums;

enum MpesaTransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REVERSED = 'reversed';
    case UNALLOCATED = 'unallocated';
}
