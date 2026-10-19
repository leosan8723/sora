<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswords();
        $this->configureRateLimiters();
        $this->configureAuthorization();
    }

    /**
     * Baseline password policy for account creation and password changes.
     */
    private function configurePasswords(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }

    /**
     * Rate limiters for the public authentication endpoints.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('auth-password', fn (Request $request) => Limit::perMinute(5)->by(
            ($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip()
        ));

        // Email verification resend: deliberately tight, one live link per
        // user is enough.
        RateLimiter::for('auth-email', fn (Request $request) => Limit::perMinute(3)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip(),
        ));

        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(20)->by(
            $request->user()?->getAuthIdentifier() ?? $request->ip()
        ));

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(10)->by($request->ip()),
        ]);

        RateLimiter::for('reviews', fn (Request $request) => [
            Limit::perHour(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
            Limit::perDay(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ]);

        // Abuse reporting is cheap for good actors but a dedicated limiter
        // keeps a compromised account from spraying the admin queue.
        RateLimiter::for('review-reports', fn (Request $request) => [
            Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
            Limit::perHour(20)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ]);

        // Sensitive, one-per-keystroke admin actions such as issuing
        // password setup links.
        RateLimiter::for('admin-actions', fn (Request $request) => [
            Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ]);
    }

    /**
     * Coarse-grained role gates. Resource-level policies are added per phase.
     */
    private function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->role === UserRole::Admin);

        Gate::define('access-consultant', fn (User $user) => in_array($user->role, [UserRole::Consultant, UserRole::Admin], true));
    }
}
