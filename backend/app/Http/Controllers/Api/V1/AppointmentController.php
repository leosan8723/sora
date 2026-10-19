<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AppointmentIndexRequest;
use App\Http\Requests\Booking\CancelAppointmentRequest;
use App\Http\Requests\Booking\RescheduleAppointmentRequest;
use App\Http\Requests\Booking\StoreAppointmentRequest;
use App\Http\Requests\Booking\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\ConsultationType;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    private const RELATIONS = ['consultantProfile.user', 'consultationType', 'client'];

    /**
     * Role-aware listing. A client sees their own bookings; a consultant sees
     * bookings made with their consultant profile; admins see everything.
     */
    public function index(AppointmentIndexRequest $request): AnonymousResourceCollection
    {
        $now = CarbonImmutable::now();
        $scope = (string) $request->validated('scope', 'upcoming');

        $query = Appointment::query()
            ->with(self::RELATIONS)
            ->forParticipant($request->user())
            ->when(
                $request->filled('status'),
                fn (Builder $builder): Builder => $builder->where('status', $request->validated('status')),
            );

        match ($scope) {
            'past' => $query->past($now),
            'all' => $query->orderByDesc('starts_at'),
            default => $query->upcoming($now),
        };

        return AppointmentResource::collection(
            $query->paginate($request->integer('per_page', 10))->withQueryString(),
        );
    }

    public function show(Request $request, string $appointment): AppointmentResource
    {
        return new AppointmentResource($this->resolve($request, $appointment)->load(self::RELATIONS));
    }

    public function store(StoreAppointmentRequest $request, BookingService $service): JsonResponse
    {
        $type = ConsultationType::query()->findOrFail((int) $request->validated('consultation_type_id'));

        $appointment = $service->book(
            $request->user(),
            $type,
            CarbonImmutable::parse((string) $request->validated('starts_at')),
            $request->validated('notes'),
        );

        return AppointmentResource::make($appointment->load(self::RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(
        CancelAppointmentRequest $request,
        string $appointment,
        BookingService $service,
    ): AppointmentResource {
        $appointment = $this->resolve($request, $appointment);

        Gate::authorize('cancel', $appointment);

        $appointment = $service->cancel(
            $request->user(),
            $appointment,
            $request->validated('cancellation_reason'),
        );

        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        string $appointment,
        BookingService $service,
    ): AppointmentResource {
        $appointment = $this->resolve($request, $appointment);

        Gate::authorize('reschedule', $appointment);

        $appointment = $service->reschedule(
            $request->user(),
            $appointment,
            CarbonImmutable::parse((string) $request->validated('starts_at')),
        );

        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    /**
     * Consultant-driven transitions (confirm/complete). Authorization lives in
     * `AppointmentPolicy::updateStatus` (consultant owner or admin); invalid
     * state transitions are rejected by the booking service with a 422.
     */
    public function updateStatus(
        UpdateAppointmentStatusRequest $request,
        string $appointment,
        BookingService $service,
    ): AppointmentResource {
        $appointment = $this->resolve($request, $appointment);

        Gate::authorize('updateStatus', $appointment);

        $appointment = $service->transitionStatus(
            $appointment,
            AppointmentStatus::from((string) $request->validated('status')),
        );

        return new AppointmentResource($appointment->load(self::RELATIONS));
    }

    /**
     * Resolve an appointment the current user is allowed to see. Non-owners get
     * a 404 rather than 403 so the endpoint does not confirm that an
     * appointment they do not own exists (no IDOR oracle).
     */
    private function resolve(Request $request, string $appointment): Appointment
    {
        $user = $request->user();

        $query = Appointment::query()->whereKey($appointment);

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        } elseif ($user->isConsultant()) {
            $query->whereHas('consultantProfile', fn (Builder $profile) => $profile->where('user_id', $user->id));
        } elseif (! $user->isAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query->firstOrFail();
    }
}
