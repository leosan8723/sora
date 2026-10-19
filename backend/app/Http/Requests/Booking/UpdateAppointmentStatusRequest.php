<?php

namespace App\Http\Requests\Booking;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only the consultant-facing transitions are accepted here; cancellation is
     * handled by the dedicated cancel endpoint.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Completed->value,
                ]),
            ],
        ];
    }
}
