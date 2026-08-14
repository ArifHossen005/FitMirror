<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // Fully-qualified: this trait's name collides with the
    // Illuminate\Contracts\Auth\MustVerifyEmail interface imported above —
    // the interface declares the contract, this trait provides the default
    // getEmailForVerification()/sendEmailVerificationNotification() implementation.
    use \Illuminate\Auth\MustVerifyEmail;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'locale',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Deliberately no `@return array<string, string>` PHPDoc — see
     * App\Models\SuperAdmin::casts() for why that annotation defeats
     * Larastan's enum-cast detection (Decision D-12).
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status->isUsable();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * True for the user that owns the tenant (Tenant::owner_id), not just
     * any user with a role called "owner" — 2FA is mandated for this
     * specific relationship regardless of what Phase 2.C's role system
     * later calls it.
     */
    public function isTenantOwner(): bool
    {
        if ($this->tenant_id === null) {
            return false;
        }

        return Tenant::query()
            ->whereKey($this->tenant_id)
            ->where('owner_id', $this->id)
            ->exists();
    }
}
