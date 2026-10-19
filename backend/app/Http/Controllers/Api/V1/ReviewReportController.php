<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewReportRequest;
use App\Models\ConsultantReview;
use App\Models\ReviewReport;
use Illuminate\Http\JsonResponse;

/**
 * Report submission for published reviews. A report never changes the
 * review itself; an admin triages it via the admin endpoints (resolve or
 * dismiss). Reporting an already-reported review is rejected as a 422 by
 * StoreReviewReportRequest.
 */
class ReviewReportController extends Controller
{
    public function store(StoreReviewReportRequest $request, ConsultantReview $review): JsonResponse
    {
        $report = ReviewReport::query()->create([
            'review_id' => $review->id,
            'reporter_id' => $request->user()->id,
            'reason' => (string) $request->string('reason'),
            'status' => ReviewReport::STATUS_OPEN,
        ]);

        return response()->json([
            'message' => __('reviews.reported'),
            'data' => [
                'id' => $report->id,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
