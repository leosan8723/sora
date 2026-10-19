<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A complaint about a published review, raised by an authenticated user and
 * triaged by an admin (resolve = problem confirmed, dismiss = no action).
 */
class ReviewReport extends Model
{
    /** @use HasFactory<ReviewReportFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'review_id',
        'reporter_id',
        'reason',
        'status',
    ];

    /** @return BelongsTo<ConsultantReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ConsultantReview::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
