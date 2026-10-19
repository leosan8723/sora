<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar_path',
        'is_active',
        'email_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function consultantProfile(): HasOne
    {
        return $this->hasOne(ConsultantProfile::class);
    }

    public function appointmentsAsClient(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isConsultant(): bool
    {
        return $this->role === UserRole::Consultant;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * Builds a fresh single-use verification token and emails the link to
     * the user. In the local environment the mail transport is the log
     * driver; real SMTP requires MAIL_* configuration only — no code change.
     */
    public function sendEmailVerificationNotice(): void
    {
        $plaintext = bin2hex(random_bytes(32));
        $ttlMinutes = 60;

        EmailVerificationToken::updateOrCreate(
            ['user_id' => $this->id],
            [
                'token_hash' => hash('sha256', $plaintext),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_at' => now(),
            ],
        );

        $frontend = rtrim((string) config('sora.frontend_url', env('FRONTEND_URL', '')), '/');

        if ($frontend === '') {
            throw new \RuntimeException('Frontend URL is not configured.');
        }

        $this->notify(new VerifyEmailNotification(
            verifyUrl: $frontend.'/verify-email?id='.$this->id
                .'&token='.$plaintext.'&expires='.now()->addMinutes($ttlMinutes)->getTimestamp(),
            expiresInMinutes: $ttlMinutes,
        ));
    }
}
