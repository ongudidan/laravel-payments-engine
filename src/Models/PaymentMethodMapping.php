<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodMapping extends BaseModel
{
    protected $baseTable = 'payment_method_mappings';

    protected $fillable = [
        'payment_method_id',
        'channel',
        'provider',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
