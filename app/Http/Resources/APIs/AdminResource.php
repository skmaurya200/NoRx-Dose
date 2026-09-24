<?php

namespace App\Http\Resources\APIs;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Admin
 */
class AdminResource extends JsonResource
{
    /**
     * An explicit allow-list. Adding a column to tbl_admins must never leak it
     * into an API response by accident.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'initial' => $this->initial(),
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
