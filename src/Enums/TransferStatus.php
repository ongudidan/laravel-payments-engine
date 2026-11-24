<?php

namespace Ongudidan\PaymentsEngine\Enums;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
