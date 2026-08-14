<?php

namespace App\Models;

use App\Enums\SuperAdminPermission;
use App\Enums\SuperAdminRole;
use App\Enums\SuperAdminStatus;
use Database\Factories\SuperAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The Product Owner / platform-operator account. Authenticates through the
 * `super_admin` guard (config/auth.php) — entirely separate from
 * App\Models\User, which represents tenant-side accounts. Never share a
 * Sanctum token or session between the two.
 */
class SuperAdmin extends Authenticatable
{
    /** @use HasFactory<SuperAdminFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Deliberately no `@return array<string, string>` PHPDoc here — that
     * annotation (copied from the default User model, where every cast
     * value genuinely is a plain string) widens Larastan's view of this
     * array and defeats its enum-cast detection for `role`/`status`,
     * silently turning $this->role back into an untyped string everywhere
     * in the codebase. Let Larastan infer types from the literal array
     * below instead.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => SuperAdminRole::class,
            'status' => SuperAdminStatus::class,
            // Encrypted at rest — a 2FA secret or recovery code leaking from
            // a database dump would otherwise let an attacker bypass 2FA
            // entirely for the platform's own admin accounts.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === SuperAdminStatus::Active;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasPermission(SuperAdminPermission $permission): bool
    {
        return in_array($permission, $this->role->permissions(), true);
    }
}
