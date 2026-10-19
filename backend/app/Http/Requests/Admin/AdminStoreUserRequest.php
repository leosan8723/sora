<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AdminStoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            // Mainly for provisioning consultants; clients self-register.
            'role' => ['required', 'string', 'in:consultant,admin'],
            // Optional: when omitted, the consultant completes their password
            // via the one-time setup link (POST /admin/users/{id}/password-link).
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => __('admin.role_in'),
        ];
    }
}
