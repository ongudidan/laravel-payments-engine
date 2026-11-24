<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Ongudidan\PaymentsEngine\Models\MpesaTransaction;

class MpesaStatementReconciliationService
{
    /**
     * Compare parsed Safaricom statement rows against system MpesaTransaction records.
     *
     * @param  array<int, array{receipt_number: string, amount: float, phone?: string, date?: string}>  $statementRows
     */
    public function reconcileStatement(array $statementRows): array
    {
        $matched = [];
        $missingInSystem = [];
        $amountMismatches = [];

        $allSystemReceipts = MpesaTransaction::pluck('amount', 'trans_id')->toArray();
        $processedStatementReceipts = [];

        foreach ($statementRows as $row) {
            $receipt = trim($row['receipt_number'] ?? '');
            if (empty($receipt)) {
                continue;
            }

            $statementAmount = round((float) ($row['amount'] ?? 0), 2);
            $processedStatementReceipts[$receipt] = true;

            if (isset($allSystemReceipts[$receipt])) {
                $systemAmount = round((float) $allSystemReceipts[$receipt], 2);

                if (abs($systemAmount - $statementAmount) < 0.01) {
                    $matched[] = [
                        'receipt_number' => $receipt,
                        'amount' => $statementAmount,
                        'status' => 'matched',
                    ];
                } else {
                    $amountMismatches[] = [
                        'receipt_number' => $receipt,
                        'statement_amount' => $statementAmount,
                        'system_amount' => $systemAmount,
                        'difference' => round($statementAmount - $systemAmount, 2),
                        'status' => 'amount_mismatch',
                    ];
                }
            } else {
                $missingInSystem[] = [
                    'receipt_number' => $receipt,
                    'amount' => $statementAmount,
                    'date' => $row['date'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'status' => 'missing_in_system',
                ];
            }
        }

        return [
            'total_statement_rows' => count($statementRows),
            'matched_count' => count($matched),
            'missing_in_system_count' => count($missingInSystem),
            'amount_mismatch_count' => count($amountMismatches),
            'matched' => $matched,
            'missing_in_system' => $missingInSystem,
            'amount_mismatches' => $amountMismatches,
        ];
    }
}
