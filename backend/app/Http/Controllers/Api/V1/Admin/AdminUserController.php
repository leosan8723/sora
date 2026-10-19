<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreUserRequest;
use App\Http\Requests\Admin\AdminUpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->with(['consultantProfile' => fn ($q) => $q->select(['id', 'user_id', 'headline', 'is_verified', 'is_active'])])
            ->orderByDesc('id');

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($q = $request->input('q')) {
            $term = mb_strtolower((string) $q);
            $query->where(function ($inner) use ($term): void {
                $inner->whereRaw('LOWER(name) LIKE ?', "%{$term}%")
                    ->orWhereRaw('LOWER(email) LIKE ?', "%{$term}%")
                    ->orWhereRaw('LOWER(phone) LIKE ?', "%{$term}%");
            });
        }

        $users = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return AdminUserResource::collection($users);
    }

    /**
     * Creates a one-time password setup/invitation link for a consultant.
     *
     * No email delivery is configured; the URL is returned exactly once so
     * the operator can hand it to the consultant out-of-band. The broker
     * stores only a hash of the token and the link expires per config.
     */
    public function passwordLink(Request $request, User $user)
    {
        if ($user->role !== UserRole::Consultant) {
            throw ValidationException::withMessages([
                'user' => __('admin.not_consultant'),
            ]);
        }

        $token = Password::createToken($user);

        $frontend = rtrim((string) config('sora.frontend_url', env('FRONTEND_URL', '')), '/');

        if ($frontend === '') {
            throw new \RuntimeException('Frontend URL is not configured.');
        }

        $minutes = (int) config('auth.passwords.users.expire', 60);

        return response()->json([
            'data' => [
                'setup_url' => $frontend.'/password-setup?token='.$token.'&email='.rawurlencode($user->email),
                'expires_in_minutes' => $minutes,
            ],
        ]);
    }

    public function store(AdminStoreUserRequest $request): AdminUserResource
    {
        $data = $request->validated();

        // Consultants may be provisioned without a password; the account is
        // unusable until the owner completes the one-time setup link.
        if (($data['password'] ?? null) !== null && $data['password'] !== '') {
            $data['password_set_at'] = now();
        } else {
            unset($data['password']);
            $data['password'] = Hash::make(Str::password(32));
        }

        $user = User::create($data);

        if ($user->role === UserRole::Consultant) {
            $user->consultantProfile()->create([]);
        }

        return new AdminUserResource($user->load('consultantProfile'));
    }

    public function update(AdminUpdateUserRequest $request, User $user): AdminUserResource
    {
        $actor = $request->user();
        $isSelf = (int) $user->id === (int) $actor->id;

        // Guards must never lock themselves out of the admin panel.
        if ($isSelf && ($request->has('role') && $request->input('role') !== UserRole::Admin->value)) {
            throw ValidationException::withMessages([
                'role' => __('admin.self_role_change'),
            ]);
        }

        if ($isSelf && $request->has('is_active') && $request->boolean('is_active') === false) {
            throw ValidationException::withMessages([
                'is_active' => __('admin.self_deactivate'),
            ]);
        }

        $user->fill($request->only(['name', 'phone', 'role', 'is_active']));
        $user->save();

        if ($user->role === UserRole::Consultant) {
            $user->consultantProfile()->firstOrCreate([]);
        }

        return new AdminUserResource($user->fresh(['consultantProfile']));
    }
}
