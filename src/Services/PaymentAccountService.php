<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Models\CashDenomination;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Models\PaymentAccountAdjustment;
use Ongudidan\PaymentsEngine\Models\PaymentAccountDenomination;
use Ongudidan\PaymentsEngine\Models\PaymentTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentTransactionFlow;

class PaymentAccountService
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Create a new payment account.
     */
    public function createAccount(array $attributes): PaymentAccount
    {
        return DB::transaction(function () use ($attributes) {
            $account = PaymentAccount::create($attributes);

            // If opening balance > 0, post opening balance to GL and update balance
            if (! empty($attributes['opening_balance']) && (float) $attributes['opening_balance'] > 0) {
                $opening = (float) $attributes['opening_balance'];
                $accountCode = $attributes['chart_of_account_code'] ?? config('payments-engine.accounts.main_vault', '1001');

                $this->ledgerService->postLine([
                    'account_code' => $accountCode,
                    'debit' => $opening,
                    'credit' => 0,
                    'entry_type' => 'OpeningBalance',
                    'reference' => 'OPEN-'.$account->id,
                    'description' => "Opening balance for payment account: {$account->name}",
                    'posting_date' => now()->toDateString(),
                    'company_id' => $account->company_id,
                    'branch_id' => $account->branch_id,
                    'created_by' => $account->user_id,
                ]);

                $account->update(['balance' => $opening]);
            }

            return $account;
        });
    }

    /**
     * Update an existing payment account.
     */
    public function updateAccount(PaymentAccount $account, array $attributes): PaymentAccount
    {
        $account->update($attributes);

        return $account->fresh();
    }

    /**
     * Toggle account active status.
     */
    public function setActive(PaymentAccount $account, bool $isActive): bool
    {
        return $account->update(['is_active' => $isActive]);
    }

    /**
     * Deposit funds into a payment account.
     */
    public function deposit(PaymentAccount $account, float $amount, string $reference, ?string $description = null): PaymentTransaction
    {
        return DB::transaction(function () use ($account, $amount, $reference, $description) {
            $tx = PaymentTransaction::create([
                'payment_account_id' => $account->id,
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'completed',
                'posted_at' => now(),
                'notes' => $description,
            ]);

            PaymentTransactionFlow::create([
                'payment_transaction_id' => $tx->id,
                'to_account_id' => $account->id,
                'amount' => $amount,
                'action' => 'deposit',
                'created_at' => now(),
            ]);

            $newBalance = (float) $account->balance + $amount;
            $account->update(['balance' => $newBalance]);

            // GL Posting
            $accountCode = $account->chartOfAccount?->code ?: config('payments-engine.accounts.bank_operating', '1010');
            $revenueCode = config('payments-engine.accounts.sales_revenue', '4000');

            $this->ledgerService->postJournal([
                [
                    'account_code' => $accountCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'Deposit',
                    'reference' => $reference,
                    'description' => $description ?: "Deposit to {$account->name}",
                ],
                [
                    'account_code' => $revenueCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'Deposit',
                    'reference' => $reference,
                    'description' => $description ?: "Deposit to {$account->name}",
                ],
            ]);

            return $tx;
        });
    }

    /**
     * Withdraw funds from a payment account.
     */
    public function withdraw(PaymentAccount $account, float $amount, string $reference, ?string $description = null): PaymentTransaction
    {
        return DB::transaction(function () use ($account, $amount, $reference, $description) {
            $tx = PaymentTransaction::create([
                'payment_account_id' => $account->id,
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'completed',
                'posted_at' => now(),
                'notes' => $description,
            ]);

            PaymentTransactionFlow::create([
                'payment_transaction_id' => $tx->id,
                'from_account_id' => $account->id,
                'amount' => $amount,
                'action' => 'withdrawal',
                'created_at' => now(),
            ]);

            $newBalance = (float) $account->balance - $amount;
            $account->update(['balance' => $newBalance]);

            // GL Posting
            $accountCode = $account->chartOfAccount?->code ?: config('payments-engine.accounts.bank_operating', '1010');
            $expenseCode = config('payments-engine.accounts.transaction_charges', '6000');

            $this->ledgerService->postJournal([
                [
                    'account_code' => $expenseCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'Withdrawal',
                    'reference' => $reference,
                    'description' => $description ?: "Withdrawal from {$account->name}",
                ],
                [
                    'account_code' => $accountCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'Withdrawal',
                    'reference' => $reference,
                    'description' => $description ?: "Withdrawal from {$account->name}",
                ],
            ]);

            return $tx;
        });
    }

    /**
     * Heal Denomination Balance:
     * Recalculates total physical cash value from denomination counts (SUM(denomination * count)),
     * cross-examines it against the ledger balance of the vault account, and flags overages or shortages.
     *
     * @param  array|null  $denominationCounts  Optional explicit array of [denomination_id => quantity] or [value => count]
     */
    public function healDenominationBalance(PaymentAccount $account, ?array $denominationCounts = null): array
    {
        return DB::transaction(function () use ($account, $denominationCounts) {
            $physicalTotal = 0.0;
            $detailedCounts = [];

            if ($denominationCounts !== null) {
                // If explicit counts are provided, sync and calculate
                foreach ($denominationCounts as $denomKey => $qty) {
                    $qty = max(0, (int) $qty);

                    // Check if denomKey is a CashDenomination id or value
                    $cashDenom = CashDenomination::find($denomKey);
                    if (! $cashDenom) {
                        $cashDenom = CashDenomination::where('value', (float) $denomKey)->first();
                    }

                    if ($cashDenom) {
                        $lineTotal = (float) $cashDenom->value * $qty;
                        $physicalTotal += $lineTotal;

                        PaymentAccountDenomination::updateOrCreate(
                            [
                                'payment_account_id' => $account->id,
                                'denomination_id' => $cashDenom->id,
                            ],
                            ['quantity' => $qty]
                        );

                        $detailedCounts[] = [
                            'denomination_id' => $cashDenom->id,
                            'value' => (float) $cashDenom->value,
                            'quantity' => $qty,
                            'subtotal' => $lineTotal,
                        ];
                    }
                }
            } else {
                // Read from stored PaymentAccountDenomination records
                $denoms = PaymentAccountDenomination::where('payment_account_id', $account->id)->get();
                foreach ($denoms as $denomRecord) {
                    $cashDenom = CashDenomination::find($denomRecord->denomination_id);
                    $value = $cashDenom ? (float) $cashDenom->value : 0.0;
                    $qty = (int) $denomRecord->quantity;
                    $lineTotal = $value * $qty;
                    $physicalTotal += $lineTotal;

                    $detailedCounts[] = [
                        'denomination_id' => $denomRecord->denomination_id,
                        'value' => $value,
                        'quantity' => $qty,
                        'subtotal' => $lineTotal,
                    ];
                }
            }

            $ledgerBalance = (float) $account->balance;
            $difference = round($physicalTotal - $ledgerBalance, 2);

            $status = 'balanced';
            if ($difference > 0.001) {
                $status = 'overage';
            } elseif ($difference < -0.001) {
                $status = 'shortage';
            }

            // Record healing adjustment record if discrepancy exists
            $adjustment = null;
            if ($status !== 'balanced') {
                $adjustment = PaymentAccountAdjustment::create([
                    'payment_account_id' => $account->id,
                    'type' => 'physical_count',
                    'old_balance' => $ledgerBalance,
                    'new_balance' => $physicalTotal,
                    'amount' => abs($difference),
                    'denominations_data' => $detailedCounts,
                    'reason' => "Physical count discrepancy detected: {$status} of {$difference}",
                    'status' => 'Pending',
                ]);
            }

            return [
                'payment_account_id' => $account->id,
                'account_name' => $account->name,
                'physical_total' => $physicalTotal,
                'ledger_balance' => $ledgerBalance,
                'difference' => $difference,
                'status' => $status,
                'is_balanced' => ($status === 'balanced'),
                'breakdown' => $detailedCounts,
                'adjustment_id' => $adjustment?->id,
            ];
        });
    }

    /**
     * Get transaction flow history and ledger records for a payment account.
     */
    public function getLedgerTransactions(PaymentAccount $account, int $limit = 50): array
    {
        $flows = PaymentTransactionFlow::where('from_account_id', $account->id)
            ->orWhere('to_account_id', $account->id)
            ->with(['transaction'])
            ->latest('id')
            ->take($limit)
            ->get();

        return [
            'account' => $account,
            'current_balance' => (float) $account->balance,
            'flows' => $flows,
        ];
    }
}
