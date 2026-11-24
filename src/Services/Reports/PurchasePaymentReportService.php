<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\SupplierWalletTransaction;

class PurchasePaymentReportService
{
    /**
     * Get supplier payments, wallet advances, and settlement audit report data.
     */
    public function getReportData(?string $startDate = null, ?string $endDate = null, ?int $supplierId = null): array
    {
        $query = SupplierWalletTransaction::query()->with(['supplier', 'creator']);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $transactions = $query->latest('id')->get();

        $totalAdvances = $transactions->where('type', 'credit')->sum('amount');
        $totalSettlements = $transactions->where('type', 'debit')->sum('amount');
        $netAdvancesOutstanding = round($totalAdvances - $totalSettlements, 2);

        return [
            'total_advances_given' => (float) $totalAdvances,
            'total_settlements_utilized' => (float) $totalSettlements,
            'net_advances_outstanding' => (float) $netAdvancesOutstanding,
            'transactions_count' => $transactions->count(),
            'transactions' => $transactions,
        ];
    }
}
