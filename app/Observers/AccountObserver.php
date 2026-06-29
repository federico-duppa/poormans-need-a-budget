<?php

namespace App\Observers;

use App\Models\Account;

class AccountObserver
{
    /**
     * When a credit card is created, ensure its payment category exists
     * within the system group "Pagos de tarjeta".
     */
    public function created(Account $account): void
    {
        if ($account->type === 'credit_card') {
            $this->ensurePaymentCategory($account);
        }
    }

    public function ensurePaymentCategory(Account $card): void
    {
        $group = $card->budget->categoryGroups()->firstOrCreate(
            ['name' => 'Pagos de tarjeta'],
            ['is_system' => true, 'position' => 900],
        );

        $group->categories()->firstOrCreate(
            ['linked_account_id' => $card->id],
            ['name' => 'Pago '.$card->name, 'position' => 0],
        );
    }
}
