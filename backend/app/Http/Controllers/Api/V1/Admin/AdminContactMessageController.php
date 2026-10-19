<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateContactMessageStatusRequest;
use App\Http\Resources\AdminContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminContactMessageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ContactMessage::query()->orderByDesc('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $messages = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return AdminContactMessageResource::collection($messages);
    }

    public function update(
        AdminUpdateContactMessageStatusRequest $request,
        ContactMessage $contactMessage,
    ): AdminContactMessageResource {
        $contactMessage->status = $request->input('status');
        $contactMessage->save();

        return new AdminContactMessageResource($contactMessage);
    }
}
