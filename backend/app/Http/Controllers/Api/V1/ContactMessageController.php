<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

/**
 * Public contact form endpoint. Messages are stored for the admin panel
 * (Phase 4); nothing is emailed and no address is disclosed.
 */
class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $user = $request->user();

        ContactMessage::create([
            'user_id' => $user?->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
        ]);

        return response()->json([
            'message' => __('contact.received'),
        ], 201);
    }
}
