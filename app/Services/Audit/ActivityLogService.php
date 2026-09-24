<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Support\SqlLike;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes and reads the audit trail.
 *
 * record() is the only way a row is written. It takes the request context
 * itself (IP, browser, route) and never a request payload, and it scrubs the
 * context it is given, so a caller cannot put a password or a code in the log
 * by accident.
 */
class ActivityLogService
{
    /**
     * Any context key ending in one of these ("password", "new_password",
     * "otp", "device_token"...) is dropped before writing.
     */
    private const SECRET_KEYS = ['password', 'otp', 'code', 'token', 'secret', 'key', 'authorization', 'cookie'];

    /**
     * @param  array<string, scalar|null>  $properties
     */
    public function record(
        string $action,
        string $description,
        ?Admin $actor = null,
        string $status = ActivityLog::STATUS_SUCCESS,
        array $properties = [],
        ?string $username = null,
    ): ?ActivityLog {
        $request = request();

        try {
            return ActivityLog::query()->create([
                'admin_id' => $actor?->getKey(),
                'username' => Str::limit($username ?? $actor?->username ?? $actor?->email ?? '', 191, '') ?: null,
                'role' => $actor?->role,
                'action' => $action,
                'description' => Str::limit($description, 500, ''),
                'status' => $status,
                'ip_address' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request ? Str::limit((string) $request->userAgent(), 500, '') : null,
                'method' => $request instanceof Request ? $request->method() : null,
                // Path only: a query string can carry a sealed filter or a token.
                'url' => $request instanceof Request ? Str::limit('/'.ltrim($request->path(), '/'), 500, '') : null,
                'properties' => $this->scrub($properties) ?: null,
            ]);
        } catch (Throwable $exception) {
            // An audit write must never take the action it describes down with
            // it. The failure is reported without the context that caused it.
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters) {
                $pattern = SqlLike::contains($filters['search']);

                $query->where(fn (Builder $inner) => $inner
                    ->where('description', 'like', $pattern)
                    ->orWhere('username', 'like', $pattern)
                    ->orWhere('action', 'like', $pattern)
                    ->orWhere('ip_address', 'like', $pattern));
            })
            ->when(filled($filters['admin_id'] ?? null), fn (Builder $q) => $q->where('admin_id', (int) $filters['admin_id']))
            ->when(filled($filters['role'] ?? null), fn (Builder $q) => $q->where('role', (string) $filters['role']))
            ->when(filled($filters['action'] ?? null), fn (Builder $q) => $q->where('action', (string) $filters['action']))
            ->when(filled($filters['status'] ?? null), fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when(filled($filters['ip'] ?? null), fn (Builder $q) => $q->where('ip_address', (string) $filters['ip']))
            ->when($this->date($filters['from'] ?? null), fn (Builder $q, Carbon $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($this->date($filters['to'] ?? null), fn (Builder $q, Carbon $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();
    }

    /**
     * The actions that actually occur, for the filter dropdown.
     *
     * @return Collection<int, string>
     */
    public function actions(): Collection
    {
        return ActivityLog::query()->distinct()->orderBy('action')->pluck('action');
    }

    /**
     * Deletes the given rows and records that it happened. The record is a new
     * insert, not a delete, so it can never trigger itself.
     *
     * @param  array<int, int>  $ids
     */
    public function delete(array $ids, Admin $actor): int
    {
        $deleted = ActivityLog::query()->whereKey($ids)->delete();

        if ($deleted > 0) {
            $this->record(
                ActivityLog::ADMIN_DELETED_ACTIVITY_LOG,
                $deleted === 1
                    ? 'Admin deleted an activity log entry.'
                    : "Admin deleted {$deleted} activity log entries.",
                $actor,
                properties: ['count' => $deleted, 'ids' => Str::limit(implode(',', $ids), 180)],
            );
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, scalar|null>
     */
    private function scrub(array $properties): array
    {
        return collect($properties)
            ->reject(fn (mixed $value, string $key) => Str::endsWith(Str::lower($key), self::SECRET_KEYS))
            ->filter(fn (mixed $value) => $value === null || is_scalar($value))
            ->all();
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(10, min(100, (int) ($filters['per_page'] ?? 25)));
    }
}
