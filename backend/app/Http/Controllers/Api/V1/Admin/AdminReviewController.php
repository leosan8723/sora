<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminReviewIndexRequest;
use App\Http\Requests\Admin\AdminUpdateReviewRequest;
use App\Http\Resources\AdminReviewResource;
use App\Models\ConsultantReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Review moderation queue. Approving a review is what makes it publicly
 * visible; rejecting or hiding withdraws it without touching the row.
 */
class AdminReviewController extends Controller
{
    private const RELATIONS = ['client', 'consultantProfile.user', 'appointment'];

    public function index(AdminReviewIndexRequest $request): AnonymousResourceCollection
    {
        $reviews = ConsultantReview::query()
            ->with(self::RELATIONS)
            ->when(
                $request->validated('status'),
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReviewStatus::Pending->value])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return AdminReviewResource::collection($reviews);
    }

    public function update(AdminUpdateReviewRequest $request, ConsultantReview $review): JsonResponse
    {
        $status = ReviewStatus::from((string) $request->string('status'));

        $review->forceFill([
            'status' => $status,
            'moderated_at' => now(),
        ])->save();

        return response()->json([
            'message' => __('admin.reviews.status_updated'),
            'data' => new AdminReviewResource($review->load(self::RELATIONS)),
        ]);
    }

    public function destroy(ConsultantReview $review): JsonResponse
    {
        $review->delete();

        return response()->json(['message' => __('admin.reviews.deleted')]);
    }
}
