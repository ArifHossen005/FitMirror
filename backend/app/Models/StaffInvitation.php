<?php

namespace App\Models;

use App\Enums\StaffInvitationStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StaffInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffInvitation extends Model
{
    /** @use HasFactory<StaffInvitationFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'email',
        'name',
        'role',
        'token_hash',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => StaffInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->status === StaffInvitationStatus::Pending && $this->expires_at->isFuture();
    }

    /**
     * Generates a raw token to mail out and returns it alongside the hash
     * that gets persisted — the raw value is never stored, only ever
     * returned once to the caller that must email it immediately.
     *
     * @return array{token: string, hash: string}
     */
    public static function generateToken(): array
    {
        $token = Str::random(48);

        return ['token' => $token, 'hash' => hash('sha256', $token)];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
