<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'different:current_password', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Verify the supplied current password against the authenticated user.
     * The default `current_password` rule targets the session guard, which is
     * not in use for token-based API requests, so we check it explicitly.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('current_password')) {
                    return;
                }

                if (! Hash::check($this->input('current_password'), $this->user()->password)) {
                    $validator->errors()->add('current_password', 'The provided password does not match your current password.');
                }
            },
        ];
    }
}
