<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminReviewReportResource;
use App\Models\ReviewReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Triage queue for review reports. Resolving or dismissing only marks the
 * report; the review itself is moderated separately through
 * AdminReviewController, and nothing is emailed.
 */
class AdminReviewReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = ReviewReport::query()
            ->with([
                'review.client:id,name',
                'review.consultantProfile.user:id,name',
                'reporter:id,name,email',
            ])
            ->when(
                $status = $request->input('status'),
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReviewReport::STATUS_OPEN])
            ->orderByDesc('id')
            ->paginate(min(50, max(1, (int) $request->input('per_page', 20))))
            ->withQueryString();

        return AdminReviewReportResource::collection($reports);
    }

    public function resolve(ReviewReport $report): JsonResponse
    {
        if ($report->status !== ReviewReport::STATUS_RESOLVED) {
            $report->status = ReviewReport::STATUS_RESOLVED;
            $report->save();
        }

        return response()->json([
            'message' => __('admin.reports.resolved'),
            'data' => new AdminReviewReportResource($report->load([
                'review.client:id,name',
                'review.consultantProfile.user:id,name',
                'reporter:id,name,email',
            ])),
        ]);
    }

    public function dismiss(ReviewReport $report): JsonResponse
    {
        if ($report->status !== ReviewReport::STATUS_DISMISSED) {
            $report->status = ReviewReport::STATUS_DISMISSED;
            $report->save();
        }

        return response()->json([
            'message' => __('admin.reports.dismissed'),
            'data' => new AdminReviewReportResource($report->load([
                'review.client:id,name',
                'review.consultantProfile.user:id,name',
                'reporter:id,name,email',
            ])),
        ]);
    }
}
