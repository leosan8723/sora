<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreConsultantReviewRequest;
use App\Http\Requests\Reviews\UpdateConsultantReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultantReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Public read + verified-client write endpoints for consultant reviews.
 */
class ConsultantReviewController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Approved reviews for one discoverable consultant. The aggregate rating
     * is merged into the pagination `meta` so clients get list + metrics from
     * a single request.
     */
    public function index(Request $request, string $consultant): JsonResponse
    {
        $profile = $this->resolveProfile($consultant);

        $query = $profile->reviews()
            ->where('status', ReviewStatus::Approved->value);

        // Signed-in authors additionally see their own still-unpublished
        // reviews so they can edit or remove them.
        if ($request->boolean('mine') && $request->user() !== null) {
            $query->orWhere(function (Builder $inner) use ($request, $profile): void {
                $inner->where('client_id', $request->user()->id)
                    ->where('consultant_profile_id', $profile->id);
            });
        }

        $reviews = $query
            ->with(['client'])
            ->orderByDesc('id')
            ->paginate(min(self::PER_PAGE, $request->integer('per_page', self::PER_PAGE)))
            ->withQueryString();

        $payload = ReviewResource::collection($reviews)->response()->getData(true);

        $payload['meta'] = array_merge($payload['meta'] ?? [], [
            'avg_rating' => $this->averageRating($profile),
            'reviews_count' => $profile->approvedReviews()->count(),
        ]);

        return response()->json($payload);
    }

    /**
     * The caller's completed appointments with this consultant that are still
     * open for a review. Used by the UI to anchor a new review.
     */
    public function reviewableAppointments(Request $request, string $consultant): JsonResponse
    {
        $profile = $this->resolveProfile($consultant);

        $appointments = Appointment::query()
            ->with('consultationType:id,title')
            ->where('client_id', $request->user()->id)
            ->where('consultant_profile_id', $profile->id)
            ->where('status', AppointmentStatus::Completed->value)
            ->whereDoesntHave('review')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'consultation_type_title' => $appointment->consultationType?->title,
            ]);

        return response()->json(['data' => $appointments]);
    }

    /**
     * Submit a review. It stays invisible to the public until an admin
     * approves it.
     */
    public function store(StoreConsultantReviewRequest $request, string $consultant): JsonResponse
    {
        $profile = $this->resolveProfile($consultant);
        $appointment = Appointment::query()->findOrFail($request->integer('appointment_id'));

        $review = $profile->reviews()->create([
            'client_id' => $request->user()->id,
            'appointment_id' => $appointment->id,
            'rating' => $request->integer('rating'),
            'body' => (string) $request->string('body'),
            'status' => ReviewStatus::Pending,
        ]);

        return response()->json([
            'message' => __('reviews.submitted'),
            'data' => new ReviewResource($review->load('client')),
        ], 201);
    }

    /**
     * Update the caller's own review. Any edit re-enters moderation so an
     * approved text can never be replaced with unchecked content.
     */
    public function update(UpdateConsultantReviewRequest $request, ConsultantReview $review): JsonResponse
    {
        $review->forceFill([
            'rating' => $request->integer('rating'),
            'body' => (string) $request->string('body'),
            'status' => ReviewStatus::Pending,
            'moderated_at' => null,
        ])->save();

        return response()->json([
            'message' => __('reviews.updated'),
            'data' => new ReviewResource($review->load('client')),
        ]);
    }

    public function destroy(Request $request, ConsultantReview $review): JsonResponse
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => __('reviews.deleted')]);
    }

    /**
     * Resolve the route-bound consultant, keeping non-discoverable profiles
     * indistinguishable from missing ones.
     */
    private function resolveProfile(string $consultant): ConsultantProfile
    {
        return ConsultantProfile::query()
            ->whereKey($consultant)
            ->discoverable()
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->firstOrFail();
    }

    private function averageRating(ConsultantProfile $profile): ?float
    {
        $average = $profile->approvedReviews()->avg('rating');

        return $average === null ? null : round((float) $average, 1);
    }
}
