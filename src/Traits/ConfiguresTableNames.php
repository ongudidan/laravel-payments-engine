<?php

namespace Ongudidan\PaymentsEngine\Traits;

trait ConfiguresTableNames
{
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (isset($this->baseTable)) {
            return $prefix.$this->baseTable;
        }

        return parent::getTable();
    }

    /**
     * Resolve host application table name from configuration.
     */
    public static function hostTable(string $key, string $default = ''): string
    {
        return config("payments-engine.tables.{$key}", $default ?: $key);
    }

    /**
     * Resolve package table name with prefix.
     */
    public static function packageTable(string $baseTable): string
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        return $prefix.$baseTable;
    }
}
