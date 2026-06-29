<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyBudgetProvisioner
{
    /**
     * Normalized list of allowed emails.
     *
     * @return array<int, string>
     */
    public static function allowedEmails(): array
    {
        return config('budget.allowed_emails', []);
    }

    /**
     * Is the email allowed to log in?
     */
    public static function isAllowed(string $email): bool
    {
        return in_array(strtolower(trim($email)), static::allowedEmails(), true);
    }

    /**
     * Create/update the user from the Google data and associate them with the
     * family budget. The first email in the whitelist is the administrator.
     *
     * @param  array{name?: string|null, email: string, google_id?: string|null, avatar?: string|null}  $data
     */
    public function provision(array $data): User
    {
        $email = strtolower(trim($data['email']));

        return DB::transaction(function () use ($data, $email) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $data['name'] ?: ($user->name ?: strtok($email, '@'));
            $user->google_id = $data['google_id'] ?? $user->google_id;
            $user->avatar = $data['avatar'] ?? $user->avatar;
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();

            $budget = $this->familyBudget();

            if (! $budget->users()->whereKey($user->id)->exists()) {
                $allowed = static::allowedEmails();
                $role = (isset($allowed[0]) && $allowed[0] === $email) ? 'admin' : 'member';
                $budget->users()->attach($user->id, ['role' => $role]);
            }

            return $user;
        });
    }

    /**
     * The family budget (MVP: single). Creates it (with default
     * categories) if it does not yet exist.
     */
    public function familyBudget(): Budget
    {
        $budget = Budget::query()->firstOrCreate(
            [],
            [
                'name' => 'Presupuesto familiar',
                'base_currency' => config('budget.base_currency', 'ARS'),
            ]
        );

        if ($budget->wasRecentlyCreated) {
            $this->seedDefaultCategories($budget);
        }

        return $budget;
    }

    /**
     * Typical initial categories of a family budget.
     */
    protected function seedDefaultCategories(Budget $budget): void
    {
        $groups = [
            'Gastos fijos' => ['Alquiler / Expensas', 'Servicios (luz/gas/agua)', 'Internet / Cable', 'Telefonía'],
            'Día a día' => ['Supermercado', 'Transporte', 'Comida afuera', 'Salud'],
            'Ahorros y metas' => ['Fondo de emergencia', 'Vacaciones'],
            'Suscripciones' => ['Streaming'],
        ];

        $groupPosition = 0;

        foreach ($groups as $groupName => $categories) {
            $group = $budget->categoryGroups()->create([
                'name' => $groupName,
                'position' => $groupPosition++,
            ]);

            $categoryPosition = 0;
            foreach ($categories as $categoryName) {
                $group->categories()->create([
                    'name' => $categoryName,
                    'position' => $categoryPosition++,
                ]);
            }
        }
    }
}
