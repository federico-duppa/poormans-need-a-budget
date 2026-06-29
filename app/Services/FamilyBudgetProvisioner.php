<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyBudgetProvisioner
{
    /**
     * Lista normalizada de emails habilitados.
     *
     * @return array<int, string>
     */
    public static function allowedEmails(): array
    {
        return config('budget.allowed_emails', []);
    }

    /**
     * ¿El email está habilitado para entrar?
     */
    public static function isAllowed(string $email): bool
    {
        return in_array(strtolower(trim($email)), static::allowedEmails(), true);
    }

    /**
     * Crea/actualiza el usuario a partir de los datos de Google y lo asocia al
     * presupuesto familiar. El primer email de la whitelist es administrador.
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
     * El presupuesto familiar (MVP: único). Lo crea si todavía no existe.
     */
    public function familyBudget(): Budget
    {
        return Budget::query()->firstOrCreate(
            [],
            [
                'name' => 'Presupuesto familiar',
                'base_currency' => config('budget.base_currency', 'ARS'),
            ]
        );
    }
}
