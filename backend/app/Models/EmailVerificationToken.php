<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email-verification token per user. The database stores only a SHA-256
 * of the token, mirroring the password-broker approach, and the row is
 * deleted as soon as it is used, so links are genuinely single-use.
 */
class EmailVerificationToken extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'user_id';

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
