<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ConsultantReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verified consultant review: each row points at exactly one completed
 * appointment (unique index) made by the client that wrote the review.
 */
class ConsultantReview extends Model
{
    /** @use HasFactory<ConsultantReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'consultant_profile_id',
        'client_id',
        'appointment_id',
        'rating',
        'body',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => ReviewStatus::class,
        'moderated_at' => 'datetime',
    ];

    /** @return BelongsTo<ConsultantProfile, $this> */
    public function consultantProfile(): BelongsTo
    {
        return $this->belongsTo(ConsultantProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Public listing scope: approved reviews only, newest first.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', ReviewStatus::Approved->value);
    }

    /**
     * The reviewer's public display name: given name plus the initial of the
     * surname. Anonymity is intentionally not offered — every review is
     * verified — but full names are still withheld.
     */
    public function clientDisplayName(): string
    {
        $parts = array_values(array_filter(explode(' ', trim((string) $this->client->name))));

        if ($parts === []) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return implode(' ', [$parts[0], mb_substr((string) end($parts), 0, 1).'.']);
    }
}
