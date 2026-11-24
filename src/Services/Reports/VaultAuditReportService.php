<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\PaymentAccountService;

class VaultAuditReportService
{
    public function __construct(protected PaymentAccountService $accountService) {}

    /**
     * Vault Physical Audit: compares actual physical denomination counts against system book balance.
     */
    public function auditVault(PaymentAccount $account): array
    {
        return $this->accountService->healDenominationBalance($account);
    }

    /**
     * Audit all active vault accounts.
     */
    public function auditAllVaults(): array
    {
        $vaults = PaymentAccount::where('is_vault', true)->orWhere('has_denominations', true)->get();
        $results = [];

        foreach ($vaults as $vault) {
            $results[] = $this->auditVault($vault);
        }

        return $results;
    }
}
