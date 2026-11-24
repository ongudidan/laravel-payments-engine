<?php

namespace Ongudidan\PaymentsEngine\Database\Seeders;

use Illuminate\Database\Seeder;
use Ongudidan\PaymentsEngine\Models\CashDenomination;

class CashDenominationsSeeder extends Seeder
{
    public function run(): void
    {
        $denominations = [
            ['type' => 'bill', 'value' => 1000.00, 'currency' => 'KES'],
            ['type' => 'bill', 'value' => 500.00, 'currency' => 'KES'],
            ['type' => 'bill', 'value' => 200.00, 'currency' => 'KES'],
            ['type' => 'bill', 'value' => 100.00, 'currency' => 'KES'],
            ['type' => 'bill', 'value' => 50.00, 'currency' => 'KES'],
            ['type' => 'coin', 'value' => 40.00, 'currency' => 'KES'],
            ['type' => 'coin', 'value' => 20.00, 'currency' => 'KES'],
            ['type' => 'coin', 'value' => 10.00, 'currency' => 'KES'],
            ['type' => 'coin', 'value' => 5.00, 'currency' => 'KES'],
            ['type' => 'coin', 'value' => 1.00, 'currency' => 'KES'],
        ];

        foreach ($denominations as $denom) {
            CashDenomination::firstOrCreate(
                ['value' => $denom['value'], 'currency' => $denom['currency']],
                $denom
            );
        }
    }
}
