<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed-in panel browser session. Written by
 * App\Services\Audit\PresenceService; the session id itself is never stored.
 */
class AdminSession extends Model
{
    protected $table = 'tbl_admin_sessions';

    protected $fillable = [
        'admin_id',
        'session_hash',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'last_seen_at',
        'logged_out_at',
        'page_views',
        'last_page',
        'last_url',
    ];

    protected $hidden = [
        'session_hash',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'page_views' => 'integer',
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
     * Signed in right now: not signed out, and seen within the session
     * lifetime - after that the session itself has expired server-side.
     *
     * @param  Builder<AdminSession>  $query
     */
    public function scopeOnline(Builder $query): void
    {
        $query->whereNull('logged_out_at')
            ->where('last_seen_at', '>=', Carbon::now()->subMinutes(max(1, (int) config('session.lifetime'))));
    }

    /**
     * Did something in the last few minutes, rather than merely still signed in.
     */
    public function isActive(): bool
    {
        return $this->last_seen_at->greaterThanOrEqualTo(Carbon::now()->subMinutes(5));
    }
}
