<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit entry. Append-only: there is no updated_at, and nothing in the
 * application edits a row after it is written.
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';

    public const LOGIN_FAILED = 'LOGIN_FAILED';

    public const LOGOUT = 'LOGOUT';

    public const OTP_SENT = 'OTP_SENT';

    public const OTP_VERIFIED = 'OTP_VERIFIED';

    public const OTP_FAILED = 'OTP_FAILED';

    public const USER_CREATED = 'USER_CREATED';

    public const USER_UPDATED = 'USER_UPDATED';

    public const USER_ACTIVATED = 'USER_ACTIVATED';

    public const USER_DEACTIVATED = 'USER_DEACTIVATED';

    public const USER_DELETED = 'USER_DELETED';

    public const USER_DEVICES_REVOKED = 'USER_DEVICES_REVOKED';

    public const ADMIN_DELETED_ACTIVITY_LOG = 'ADMIN_DELETED_ACTIVITY_LOG';

    public const PAGE_VIEWED = 'PAGE_VIEWED';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $table = 'tbl_activity_logs';

    protected $fillable = [
        'admin_id',
        'username',
        'role',
        'action',
        'description',
        'status',
        'ip_address',
        'user_agent',
        'method',
        'url',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUCCESS => 'Success',
            self::STATUS_FAILED => 'Failed',
        ];
    }
}
