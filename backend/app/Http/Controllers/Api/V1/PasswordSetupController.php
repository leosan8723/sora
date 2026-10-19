<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Completes the one-time consultant password setup / password reset using a
 * password-broker token handed over out-of-band by an administrator.
 *
 * Security properties:
 * - The broker stores only password hashes of tokens, enforces single use
 *   and expiry, and throttles repeated attempts.
 * - A failed attempt returns the same generic message for invalid tokens,
 *   expired tokens and unknown emails, so user existence is never revealed.
 * - On success every existing Sanctum token is revoked.
 */
class PasswordSetupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', PasswordRule::min(12)->letters()->mixedCase()->numbers()],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password, // hashed by the model cast
                    'password_set_at' => now(),
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'data' => ['message' => __('passwords.reset')],
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __('passwords.throttled'),
            ]);
        }

        // Invalid token, expired token, or unknown email: identical generic
        // response so account existence is not leaked.
        throw ValidationException::withMessages([
            'token' => __('passwords.token'),
        ]);
    }
}
