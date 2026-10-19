<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialtyResource;
use App\Models\Specialty;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpecialtyController extends Controller
{
    /**
     * Public catalog of consultation specialties.
     */
    public function index(): AnonymousResourceCollection
    {
        $specialties = Specialty::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return SpecialtyResource::collection($specialties);
    }
}
