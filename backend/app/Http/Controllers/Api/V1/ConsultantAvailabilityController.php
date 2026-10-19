<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\StoreAvailabilityRuleRequest;
use App\Http\Requests\Consultant\UpdateAvailabilityRuleRequest;
use App\Http\Resources\ConsultantAvailabilityRuleResource;
use App\Models\AvailabilityRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConsultantAvailabilityController extends Controller
{
    /**
     * Weekly availability of the authenticated consultant. Inactive rules are
     * included so the owner can re-enable them.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = $request->user()->consultantProfile()->firstOrCreate([])
            ->availabilityRules()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        return ConsultantAvailabilityRuleResource::collection($rules);
    }

    public function store(StoreAvailabilityRuleRequest $request): JsonResponse
    {
        $rule = $request->user()->consultantProfile()->firstOrCreate([])
            ->availabilityRules()
            ->create($request->validated());

        return ConsultantAvailabilityRuleResource::make($rule)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAvailabilityRuleRequest $request, AvailabilityRule $availabilityRule): ConsultantAvailabilityRuleResource
    {
        Gate::authorize('update', $availabilityRule);

        $availabilityRule->fill($request->validated())->save();

        return new ConsultantAvailabilityRuleResource($availabilityRule);
    }

    public function destroy(AvailabilityRule $availabilityRule): Response
    {
        Gate::authorize('delete', $availabilityRule);

        $availabilityRule->delete();

        return response()->noContent();
    }
}
