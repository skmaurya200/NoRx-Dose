<?php

namespace App\Http\Requests\APIs\User;

use App\Models\Admin;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edit User. The same rules as Add User, with the password optional (blank
 * keeps the current one) and the status switch - which is refused for the
 * caller's own account and for Admin accounts.
 */
class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->account()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => $this->passwordRules(required: false),
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->has('is_active')) {
                    return;
                }

                $account = $this->account();

                if ($this->boolean('is_active') !== $account->is_active
                    && ! $this->user()->can('changeStatus', $account)) {
                    $validator->errors()->add('is_active', 'The status of this account cannot be changed here.');
                }
            },
        ];
    }

    /**
     * @return array{name: string, username: string, email: string, mobile_no: string, password: string|null, is_active: bool|null}
     */
    public function payload(): array
    {
        $data = $this->safe()->only(['name', 'username', 'email', 'mobile_no', 'password']);

        $data['password'] ??= null;
        $data['is_active'] = $this->has('is_active') ? $this->boolean('is_active') : null;

        return $data;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // The switch posts nothing when it is off, so the form sends a
        // hidden marker saying the switch was on screen at all.
        if ($this->boolean('status_present')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    protected function uniqueRule(string $column): mixed
    {
        return Rule::unique('tbl_admins', $column)->ignore($this->account()->getKey());
    }

    private function account(): Admin
    {
        /** @var Admin */
        return $this->route('account');
    }
}
