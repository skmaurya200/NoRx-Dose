<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Back-office account. Kept entirely separate from App\Models\User, which is
 * the storefront customer - an admin is never a customer record and the two
 * must not share a guard, a table or a password reset flow.
 */
class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * "super-admin" is the value the seeder has always written, so it stays the
     * Admin role rather than a renamed copy of it.
     */
    public const ROLE_ADMIN = 'super-admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_USER = 'user';

    protected $table = 'tbl_admins';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar_path',
        'role',
        'is_active',
    ];

    /**
     * Never serialised, so a token/response cannot leak them even if a future
     * controller returns the model directly.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'failed_login_attempts',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'sessions_revoked_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_USER => 'User',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::roles()[$this->role] ?? Str::headline((string) $this->role);
    }

    /**
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * @return HasMany<LoginOtp, $this>
     */
    public function loginOtps(): HasMany
    {
        return $this->hasMany(LoginOtp::class);
    }

    /**
     * A locked account is refused even when the password is correct.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Seconds until the lock lifts, for the Retry-After header.
     */
    public function secondsUntilUnlock(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(1, Carbon::now()->diffInSeconds($this->locked_until, false));
    }

    /**
     * Absolute URL for the avatar, or null. Files live under public/, so this
     * resolves through asset() rather than the storage disk.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset($this->avatar_path) : null;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
