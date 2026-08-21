<?php

namespace App\Models;

use App\Enums\KioskDeviceStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\KioskDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One physical kiosk (tablet or laptop) running the try-on app in a
 * branch. Authenticates with a long-lived device token rather than a user
 * session — see App\Http\Middleware\AuthenticateKioskDevice — because
 * nobody logs in at a kiosk: it is unattended hardware that must survive
 * reboots and staff turnover.
 *
 * Two secrets, deliberately handled differently:
 *   - `pairing_code` is stored in the clear. It is *displayed* in the
 *     dashboard so a staff member can type it into the kiosk, expires in
 *     minutes, and is single-use.
 *   - `device_token_hash` is a sha256 digest. The raw device token is
 *     returned exactly once, at claim time, and never recoverable
 *     afterwards — same reasoning as staff_invitations.token_hash.
 */
class KioskDevice extends Model
{
    /** @use HasFactory<KioskDeviceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Short enough to read off a screen and type on a tablet, long enough
     * that guessing one inside its lifetime is not feasible: 8 characters
     * from a 32-symbol alphabet is 2^40 possibilities.
     */
    public const PAIRING_CODE_LENGTH = 8;

    /**
     * Deliberately excludes I, O, 0 and 1 — the pairs a person reading a
     * code off a screen most often confuses.
     */
    public const PAIRING_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const PAIRING_CODE_TTL_MINUTES = 15;

    /**
     * Every display setting the kiosk app understands, with the value used
     * when a device row predates the key being added. Resolved through
     * settings() so an existing kiosk never has to be re-configured just
     * because a new setting shipped.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_SETTINGS = [
        'language' => 'bn',
        'theme' => 'light',
        'idle_timeout_seconds' => 120,
        'screensaver_playlist' => [],
        'show_branding' => true,
        'attract_loop_enabled' => true,
    ];

    /** @var list<string> */
    public const SUPPORTED_LANGUAGES = ['bn', 'en'];

    /** @var list<string> */
    public const SUPPORTED_THEMES = ['light', 'dark'];

    public const MIN_IDLE_TIMEOUT_SECONDS = 15;

    public const MAX_IDLE_TIMEOUT_SECONDS = 1800;

    /** How often a paired kiosk is expected to call the heartbeat endpoint. */
    public const HEARTBEAT_INTERVAL_SECONDS = 60;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'device_fingerprint',
        'status',
        'settings',
    ];

    protected $hidden = [
        'device_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => KioskDeviceStatus::class,
            'pairing_code_expires_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'health' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * withTrashed(): Store is soft-deleted, and a device row whose branch
     * has been removed must still resolve to *something* — every caller
     * (the policy check, the kiosk auth middleware) reaches through this
     * relation, and a null store would turn a removed branch into a
     * TypeError instead of a clean refusal. Callers that care check
     * `$device->store->trashed()` explicitly.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    /**
     * @param Builder<KioskDevice> $query
     * @return Builder<KioskDevice>
     */
    public function scopePaired(Builder $query): Builder
    {
        return $query->where('status', KioskDeviceStatus::Paired->value);
    }

    /**
     * Stored settings merged over DEFAULT_SETTINGS, so callers always get
     * a complete array and never have to null-coalesce a key themselves.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    public function hasUsablePairingCode(): bool
    {
        return $this->pairing_code !== null
            && $this->pairing_code_expires_at !== null
            && $this->pairing_code_expires_at->isFuture();
    }

    /**
     * A device is considered online if it has heartbeated within twice its
     * expected interval — one missed beat is a flaky shop Wi-Fi, two is a
     * device worth showing as offline in the dashboard.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::HEARTBEAT_INTERVAL_SECONDS * 2));
    }

    /**
     * Generates a raw device token to hand back to the kiosk once,
     * alongside the hash that gets persisted.
     *
     * @return array{token: string, hash: string}
     */
    public static function generateDeviceToken(): array
    {
        $token = Str::random(64);

        return ['token' => $token, 'hash' => self::hashDeviceToken($token)];
    }

    public static function hashDeviceToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * A pairing code that is not currently held by any other device.
     * Uniqueness is enforced by a unique index as well; this loop just
     * avoids relying on an exception for the (very rare) collision.
     */
    public static function generatePairingCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::PAIRING_CODE_LENGTH; $i++) {
                $code .= self::PAIRING_CODE_ALPHABET[random_int(0, strlen(self::PAIRING_CODE_ALPHABET) - 1)];
            }

            // withoutTenantScope(): pairing codes are globally unique
            // precisely because claim() resolves one before any tenant is
            // known, so the collision check must span every tenant too.
        } while (self::withoutTenantScope()->where('pairing_code', $code)->exists());

        return $code;
    }
}
