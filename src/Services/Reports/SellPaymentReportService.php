<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\CustomerWalletTransaction;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentTransaction;

class SellPaymentReportService
{
    /**
     * Customer payments breakdown by tender method (Cash, M-Pesa, Wallet, Bank).
     */
    public function getReportData(?string $startDate = null, ?string $endDate = null): array
    {
        $mpesaQuery = MpesaTransaction::where('status', 'completed');
        $walletQuery = CustomerWalletTransaction::where('type', 'debit');
        $paymentTxQuery = PaymentTransaction::where('status', 'completed');

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            $mpesaQuery->whereBetween('created_at', [$start, $end]);
            $walletQuery->whereBetween('created_at', [$start, $end]);
            $paymentTxQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalMpesa = (float) $mpesaQuery->sum('amount');
        $totalWallet = (float) $walletQuery->sum('amount');
        $totalGeneralPayments = (float) $paymentTxQuery->sum('amount');

        $grandTotal = round($totalMpesa + $totalWallet + $totalGeneralPayments, 2);

        return [
            'summary' => [
                'mpesa_total' => $totalMpesa,
                'wallet_total' => $totalWallet,
                'general_payments_total' => $totalGeneralPayments,
                'grand_total' => $grandTotal,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }
}
