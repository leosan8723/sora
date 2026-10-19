<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\ConsultantIndexRequest;
use App\Http\Requests\Discovery\ConsultantSlotsRequest;
use App\Http\Resources\ConsultantDetailResource;
use App\Http\Resources\ConsultantSummaryResource;
use App\Models\ConsultantProfile;
use App\Models\User;
use App\Services\Booking\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsultantController extends Controller
{
    /**
     * Public, paginated listing of discoverable consultants with optional
     * keyword search, specialty filter, and sorting.
     */
    public function index(ConsultantIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $sort = $validated['sort'] ?? 'name';
        $perPage = (int) ($validated['per_page'] ?? 12);

        $consultants = ConsultantProfile::query()
            ->discoverable()
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->when(
                $validated['specialty'] ?? null,
                fn (Builder $query, string $slug) => $query->whereHas(
                    'specialties',
                    fn (Builder $specialties) => $specialties->where('slug', $slug)
                )
            )
            ->when($validated['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.$term.'%';

                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('headline', 'like', $like)
                        ->orWhere('bio', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like));
                });
            })
            ->when($sort === 'experience', fn (Builder $query) => $query->orderByDesc('years_experience'))
            ->when($sort === 'newest', fn (Builder $query) => $query->orderByDesc('id'))
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'consultant_profiles.user_id')
            ))
            ->with(['user', 'specialties', 'consultationTypes'])
            ->withCount(['approvedReviews as approved_reviews_count'])
            ->withAvg(['approvedReviews as approved_reviews_avg_rating'], 'rating')
            ->paginate($perPage)
            ->withQueryString();

        return ConsultantSummaryResource::collection($consultants);
    }

    /**
     * Public detail of a single discoverable consultant. Non-discoverable
     * profiles are reported as not found to avoid leaking private records.
     */
    public function show(string $consultant): ConsultantDetailResource
    {
        $profile = ConsultantProfile::query()
            ->whereKey($consultant)
            ->discoverable()
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->with(['user', 'specialties', 'consultationTypes', 'availabilityRules'])
            ->withCount(['approvedReviews as approved_reviews_count'])
            ->withAvg(['approvedReviews as approved_reviews_avg_rating'], 'rating')
            ->firstOrFail();

        return new ConsultantDetailResource($profile);
    }

    /**
     * Public bookable slots for one consultation type over a Tehran date range.
     * Appointment counts and identities are never exposed — only free starts.
     */
    public function slots(
        ConsultantSlotsRequest $request,
        string $consultant,
        SlotGenerator $generator,
    ): JsonResponse {
        $profile = ConsultantProfile::query()
            ->whereKey($consultant)
            ->discoverable()
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->with(['availabilityRules', 'consultationTypes'])
            ->firstOrFail();

        $type = $profile->consultationTypes
            ->where('is_active', true)
            ->firstWhere('id', (int) $request->input('consultation_type'));

        abort_if($type === null, 404, 'Consultation type not found.');

        $timezone = $generator->timezone();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $from = $request->filled('from')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $request->input('from'), $timezone)->startOfDay()
            : $today;

        $to = $request->filled('to')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $request->input('to'), $timezone)->startOfDay()
            : $from->addDays(max(0, (int) config('booking.default_range_days', 7) - 1));

        $duration = max(1, (int) $type->duration_minutes);
        $days = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $starts = $generator->availableStarts($profile, $type, $date);

            if ($starts->isEmpty()) {
                continue;
            }

            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $date->dayOfWeek,
                'slots' => $starts
                    ->map(fn (CarbonImmutable $start): array => [
                        'starts_at' => $start->toIso8601ZuluString(),
                        'ends_at' => $start->addMinutes($duration)->toIso8601ZuluString(),
                        'label' => $start->setTimezone($timezone)->format('H:i'),
                    ])
                    ->all(),
            ];
        }

        return response()->json([
            'data' => $days,
            'meta' => [
                'timezone' => $timezone,
                'consultant_id' => $profile->id,
                'consultation_type_id' => $type->id,
                'duration_minutes' => $duration,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
