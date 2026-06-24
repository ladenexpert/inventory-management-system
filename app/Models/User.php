<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @property int $id
     */

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'created_by');
    }

    public function issuedMaterialUsages()
    {
        return $this->hasMany(Sale::class, 'issued_by');
    }

    public function isAdminRni(): bool
    {
        return $this->role === UserRole::ADMIN_RNI;
    }

    public function canAccessFinance(): bool
    {
        return $this->isAdminRni();
    }

    public function canAccessAdministration(): bool
    {
        return $this->isAdminRni();
    }

    public function isFormulator(): bool
    {
        return $this->role === UserRole::FORMULATOR;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role?->value, $roles, true);
    }
}
