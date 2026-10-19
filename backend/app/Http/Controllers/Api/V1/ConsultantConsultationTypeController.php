<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\StoreConsultationTypeRequest;
use App\Http\Requests\Consultant\UpdateConsultationTypeRequest;
use App\Http\Resources\ConsultantConsultationTypeResource;
use App\Models\ConsultationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ConsultantConsultationTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $types = $request->user()->consultantProfile()->firstOrCreate([])
            ->consultationTypes()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ConsultantConsultationTypeResource::collection($types);
    }

    public function store(StoreConsultationTypeRequest $request): JsonResponse
    {
        $type = $request->user()->consultantProfile()->firstOrCreate([])
            ->consultationTypes()
            ->create($request->validated());

        return ConsultantConsultationTypeResource::make($type)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateConsultationTypeRequest $request, ConsultationType $consultationType): ConsultantConsultationTypeResource
    {
        Gate::authorize('update', $consultationType);

        $consultationType->fill($request->validated())->save();

        return new ConsultantConsultationTypeResource($consultationType);
    }

    public function destroy(Request $request, ConsultationType $consultationType): Response
    {
        Gate::authorize('delete', $consultationType);

        $consultationType->delete();

        return response()->noContent();
    }
}
