<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAppointmentIndexRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminAppointmentController extends Controller
{
    public function index(AdminAppointmentIndexRequest $request): AnonymousResourceCollection
    {
        $query = Appointment::query()
            ->with(['consultantProfile.user:id,name', 'consultationType', 'client:id,name'])
            ->orderByDesc('starts_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($mode = $request->input('mode')) {
            $query->where('mode', $mode);
        }

        if ($profileId = $request->input('consultant_profile_id')) {
            $query->where('consultant_profile_id', (int) $profileId);
        }

        if ($q = $request->input('q')) {
            $term = mb_strtolower((string) $q);
            $query->where(function ($inner) use ($term): void {
                $inner->whereHas('client', fn ($client) => $client->whereRaw('LOWER(name) LIKE ?', "%{$term}%"))
                    ->orWhereHas('consultantProfile.user', fn ($user) => $user->whereRaw('LOWER(name) LIKE ?', "%{$term}%"));
            });
        }

        $appointments = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return AppointmentResource::collection($appointments);
    }
}
