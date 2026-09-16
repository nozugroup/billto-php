<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\BankAccount;

/**
 * Team bank accounts: `/bank-accounts`.
 */
final class BankAccounts extends Resource
{
    /**
     * Accounts, default first. Inactive ones are included only with `$activeOnly = false`.
     *
     * @return list<BankAccount>
     */
    public function list(bool $activeOnly = true): array
    {
        return $this->entities($this->transport->get('bank-accounts', ['active_only' => $activeOnly]), BankAccount::class);
    }

    public function default(): ?BankAccount
    {
        foreach ($this->list() as $account) {
            if ($account->is_default) {
                return $account;
            }
        }

        return null;
    }
}
