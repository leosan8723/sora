<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateConsultationTypeRequest;
use App\Http\Resources\AdminConsultationTypeResource;
use App\Models\ConsultationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminConsultationTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ConsultationType::query()
            ->with(['consultantProfile:id,user_id'])
            ->with('consultantProfile.user:id,name')
            ->orderBy('consultant_profile_id')
            ->orderBy('sort_order');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($mode = $request->input('mode')) {
            $query->where('mode', $mode);
        }

        if ($profileId = $request->input('consultant_profile_id')) {
            $query->where('consultant_profile_id', (int) $profileId);
        }

        $types = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return AdminConsultationTypeResource::collection($types);
    }

    public function update(
        AdminUpdateConsultationTypeRequest $request,
        ConsultationType $consultationType,
    ): AdminConsultationTypeResource {
        $consultationType->fill($request->only([
            'title',
            'description',
            'duration_minutes',
            'price_toman',
            'price_usd_cents',
            'mode',
            'is_active',
            'sort_order',
        ]));
        $consultationType->save();

        return new AdminConsultationTypeResource($consultationType->fresh(['consultantProfile.user:id,name']));
    }
}
