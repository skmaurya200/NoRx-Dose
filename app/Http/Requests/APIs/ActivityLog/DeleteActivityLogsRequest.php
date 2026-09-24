<?php

namespace App\Http\Requests\APIs\ActivityLog;

use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk delete of selected log entries, capped at one page's worth.
 */
class DeleteActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deleteAny', ActivityLog::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one log entry.',
            'ids.max' => 'Delete at most 100 entries at a time.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_map('intval', $this->validated('ids'));
    }
}
