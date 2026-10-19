<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\SyncSpecialtiesRequest;
use App\Http\Resources\ConsultantProfileResource;
use Illuminate\Http\JsonResponse;

class ConsultantSpecialtyController extends Controller
{
    /**
     * Replace the consultant's specialty selection with the provided set.
     * A PUT always answers 200, even when the profile is created on first write.
     */
    public function update(SyncSpecialtiesRequest $request): JsonResponse
    {
        $profile = $request->user()->consultantProfile()->firstOrCreate([]);

        $profile->specialties()->sync($request->validated('specialties'));

        return (new ConsultantProfileResource(
            $profile->load(['specialties', 'consultationTypes', 'availabilityRules'])
        ))->response()->setStatusCode(200);
    }
}
