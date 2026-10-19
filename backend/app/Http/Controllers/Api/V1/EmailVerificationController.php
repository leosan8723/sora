<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Single-use, expiring email verification. The public verify endpoint takes
 * `id`, `token` and `expires` from the emailed link; the authenticated
 * resend endpoint issues a fresh link to the logged-in (unverified) user.
 * User-unavailable and malformed-token requests return the same generic
 * message so the endpoint can not be used to probe registered addresses.
 */
class EmailVerificationController extends Controller
{
    private const TOKEN_TTL_MINUTES = 60;

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'token' => ['required', 'string', 'max:255'],
            'expires' => ['required', 'integer'],
        ]);

        $user = User::find($data['id']);
        $token = EmailVerificationToken::where('user_id', $data['id'])->first();
        $digest = hash('sha256', $data['token']);

        if (
            $user === null
            || $token === null
            || ! hash_equals($token->token_hash, $digest)
            || $token->expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'token' => __('auth.verify_invalid'),
            ]);
        }

        // Single-use: the row is consumed whether or not the user is already
        // verified, so a replayed link cannot succeed later.
        $token->delete();

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json([
            'data' => [
                'message' => __('auth.verify_ok'),
                'email_verified' => true,
            ],
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'data' => [
                    'message' => __('auth.verify_already'),
                    'email_verified' => true,
                ],
            ]);
        }

        $user->sendEmailVerificationNotice();

        return response()->json([
            'data' => [
                'message' => __('auth.verify_sent'),
                'email_verified' => false,
            ],
        ]);
    }
}
