<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Raised when a requested slot is a valid availability slot but is already
 * held by another active appointment (a lost race, ARCHITECTURE.md §9).
 */
class BookingConflictException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('booking.slot_conflict'));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [[
                'code' => 'booking_conflict',
                'field' => 'starts_at',
                'message' => $this->getMessage(),
            ]],
        ], 409);
    }
}
