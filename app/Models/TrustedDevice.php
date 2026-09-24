<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser that completed the one-time code step. The browser holds the raw
 * token; this row holds only its hash.
 */
class TrustedDevice extends Model
{
    protected $table = 'tbl_trusted_devices';

    protected $fillable = [
        'admin_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'verified_at',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Not revoked and not past its expiry, measured on the server clock.
     *
     * @param  Builder<TrustedDevice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
