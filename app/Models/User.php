<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Presupuestos a los que pertenece el usuario.
     *
     * @return BelongsToMany<Budget, $this>
     */
    public function budgets(): BelongsToMany
    {
        return $this->belongsToMany(Budget::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * El presupuesto familiar activo del usuario (MVP: uno solo).
     */
    public function currentBudget(): ?Budget
    {
        return $this->budgets()->first();
    }

    /**
     * ¿Es administrador de algún presupuesto?
     */
    public function isAdmin(): bool
    {
        return $this->budgets()->wherePivot('role', 'admin')->exists();
    }
}
