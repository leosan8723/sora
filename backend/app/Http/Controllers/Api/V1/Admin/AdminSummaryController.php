<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ContactMessage;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Operational summary for the admin dashboard. Counts only — no sensitive
 * content, no per-user listings.
 */
class AdminSummaryController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();

        $usersByRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $appointmentsByStatus = Appointment::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'users' => [
                'total' => (int) array_sum($usersByRole->all()),
                'clients' => (int) $usersByRole->get(UserRole::Client->value, 0),
                'consultants' => (int) $usersByRole->get(UserRole::Consultant->value, 0),
                'admins' => (int) $usersByRole->get(UserRole::Admin->value, 0),
            ],
            'consultants' => [
                'profiles' => ConsultantProfile::query()->count(),
                'verified' => (int) ConsultantProfile::query()->where('is_verified', true)->count(),
                'discoverable' => (int) ConsultantProfile::query()->where('is_active', true)->where('is_verified', true)->count(),
            ],
            'appointments' => [
                'total' => (int) array_sum($appointmentsByStatus->all()),
                'pending' => (int) $appointmentsByStatus->get(AppointmentStatus::Pending->value, 0),
                'confirmed' => (int) $appointmentsByStatus->get(AppointmentStatus::Confirmed->value, 0),
                'completed' => (int) $appointmentsByStatus->get(AppointmentStatus::Completed->value, 0),
                'cancelled' => (int) $appointmentsByStatus->get(AppointmentStatus::Cancelled->value, 0),
                'upcoming_7d' => (int) Appointment::query()
                    ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
                    ->where('starts_at', '>=', $now)
                    ->where('starts_at', '<', $now->addDays(7))
                    ->count(),
            ],
            'contact_messages' => [
                'unhandled' => (int) ContactMessage::query()->where('status', 'new')->count(),
            ],
        ];
    }
}
