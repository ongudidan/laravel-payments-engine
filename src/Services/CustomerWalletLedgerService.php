<?php

namespace Ongudidan\PaymentsEngine\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Ongudidan\PaymentsEngine\Models\CustomerWalletTransaction;

class CustomerWalletLedgerService
{
    /**
     * Build filtered query for customer wallet transactions.
     */
    public function buildFilteredQuery(array $filters): Builder
    {
        $query = CustomerWalletTransaction::query();

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'])->startOfDay();
            $end = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query->latest('id');
    }

    /**
     * Get summary metrics for filtered customer wallet transactions.
     */
    public function getSummaryMetrics(array $filters): array
    {
        $query = $this->buildFilteredQuery($filters);

        $totalCredits = (clone $query)->where('type', 'credit')->sum('amount');
        $totalDebits = (clone $query)->where('type', 'debit')->sum('amount');
        $netMovement = round($totalCredits - $totalDebits, 2);

        return [
            'total_credits' => (float) $totalCredits,
            'total_debits' => (float) $totalDebits,
            'net_movement' => $netMovement,
            'total_transactions' => (clone $query)->count(),
        ];
    }
}
