<?php

namespace App\Http\Requests\Reviews;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ConsultantReview;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A review may only be attached to one of the author's own completed
 * appointments with the consultant named in the route. These checks live in
 * the validator (not the controller) so every failure is reported as a
 * field-level 422 with a Persian message.
 */
class StoreConsultantReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id', $this->eligibleAppointment()],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'appointment_id' => __('reviews.attributes.appointment_id'),
            'rating' => __('reviews.attributes.rating'),
            'body' => __('reviews.attributes.body'),
        ];
    }

    private function eligibleAppointment(): callable
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $appointment = Appointment::query()->find($value);

            if ($appointment === null) {
                return; // handled by the exists rule
            }

            if ($appointment->client_id !== $this->user()?->id) {
                $fail(__('reviews.not_your_appointment'));

                return;
            }

            if ($appointment->status !== AppointmentStatus::Completed) {
                $fail(__('reviews.not_completed'));

                return;
            }

            if ((int) $appointment->consultant_profile_id !== (int) $this->route('consultant')) {
                $fail(__('reviews.wrong_consultant'));

                return;
            }

            if (ConsultantReview::query()->where('appointment_id', $appointment->id)->exists()) {
                $fail(__('reviews.already_reviewed'));
            }
        };
    }
}
