<?php

namespace Ongudidan\PaymentsEngine\Traits;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

trait ResolvesModels
{
    /**
     * Default model class fallback mapping.
     */
    protected static array $defaultModelMap = [
        'user' => User::class,
        'customer' => Customer::class,
        'supplier' => Supplier::class,
        'company' => Company::class,
        'branch' => Branch::class,
        'sale' => Sale::class,
    ];

    /**
     * Resolve the configured model class for a key.
     */
    public static function modelClass(string $key, string $default = ''): string
    {
        if (empty($default) && isset(static::$defaultModelMap[$key])) {
            $default = static::$defaultModelMap[$key];
        }

        return config("payments-engine.models.{$key}", $default);
    }

    /**
     * Instance wrapper to resolve model class.
     */
    protected function getModelClass(string $key, string $default = ''): string
    {
        return static::modelClass($key, $default);
    }
}
