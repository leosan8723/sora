<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateConsultantRequest;
use App\Http\Resources\AdminConsultantResource;
use App\Models\ConsultantProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminConsultantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ConsultantProfile::query()
            ->with(['user:id,name,email,is_active', 'specialties:id,name,slug'])
            ->withCount(['consultationTypes', 'appointments'])
            ->orderByDesc('id');

        if ($request->has('verified')) {
            $query->where('is_verified', $request->boolean('verified'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($q = $request->input('q')) {
            $term = mb_strtolower((string) $q);
            $query->where(function ($inner) use ($term): void {
                $inner->whereRaw('LOWER(headline) LIKE ?', "%{$term}%")
                    ->orWhereHas('user', function ($user) use ($term): void {
                        $user->whereRaw('LOWER(name) LIKE ?', "%{$term}%")
                            ->orWhereRaw('LOWER(email) LIKE ?', "%{$term}%");
                    });
            });
        }

        $profiles = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return AdminConsultantResource::collection($profiles);
    }

    public function update(
        AdminUpdateConsultantRequest $request,
        ConsultantProfile $consultant,
    ): AdminConsultantResource {
        $consultant->fill($request->only(['headline', 'bio', 'name_en', 'headline_en', 'bio_en', 'years_experience', 'is_verified', 'is_active']));

        if ($consultant->isDirty('is_verified')) {
            $consultant->verified_at = $consultant->is_verified ? now() : null;
        }

        $consultant->save();

        return new AdminConsultantResource($consultant->fresh(['user:id,name,email,is_active', 'specialties:id,name,slug']));
    }
}
