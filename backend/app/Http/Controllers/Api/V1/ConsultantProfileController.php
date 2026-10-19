<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\UpdateConsultantProfileRequest;
use App\Http\Resources\ConsultantProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultantProfileController extends Controller
{
    private const RELATIONS = ['specialties', 'consultationTypes', 'availabilityRules'];

    /**
     * The authenticated consultant's own profile.
     */
    public function show(Request $request): ConsultantProfileResource
    {
        $profile = $request->user()->consultantProfile()->first();

        abort_if($profile === null, 404, 'Consultant profile not found.');

        return new ConsultantProfileResource($profile->load(self::RELATIONS));
    }

    /**
     * Update (or provision on first write) the authenticated consultant's
     * profile. A PUT always answers 200, even when the record is created.
     */
    public function update(UpdateConsultantProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->consultantProfile()->firstOrNew([]);

        $profile->fill($request->validated())->save();

        return (new ConsultantProfileResource($profile->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(200);
    }
}
