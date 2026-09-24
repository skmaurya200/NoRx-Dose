<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending sign-in waiting for its one-time code. Only keyed hashes of the
 * code and of the challenge are stored.
 */
class LoginOtp extends Model
{
    protected $table = 'tbl_login_otps';

    protected $fillable = [
        'admin_id',
        'challenge_hash',
        'code_hash',
        'attempts',
        'send_count',
        'last_sent_at',
        'expires_at',
        'used_at',
        'invalidated_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'challenge_hash',
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'send_count' => 'integer',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'invalidated_at' => 'datetime',
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
     * Not yet used, not replaced, not expired.
     *
     * @param  Builder<LoginOtp>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->used_at === null
            && $this->invalidated_at === null
            && $this->expires_at->isFuture();
    }
}
