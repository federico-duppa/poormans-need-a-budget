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
     * Budgets the user belongs to.
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
     * The user's active family budget (MVP: only one).
     */
    public function currentBudget(): ?Budget
    {
        return $this->budgets()->first();
    }

    /**
     * Is the user an administrator of any budget?
     */
    public function isAdmin(): bool
    {
        return $this->budgets()->wherePivot('role', 'admin')->exists();
    }
}
