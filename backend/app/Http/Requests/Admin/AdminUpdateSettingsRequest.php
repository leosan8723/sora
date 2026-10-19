<?php

namespace App\Http\Requests\Admin;

use App\Enums\ConsultationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supported_modes' => ['sometimes', 'array'],
            'supported_modes.*' => ['string', Rule::in(ConsultationMode::values())],
            'contact_lines' => ['sometimes', 'array', 'max:8'],
            'contact_lines.*' => ['string', 'max:160'],
        ];
    }

    /**
     * Keep at least one supported mode so the platform can never be booked
     * into a dead end, and drop anything that is not a platform enum value.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('supported_modes')) {
                return;
            }

            $modes = (array) $this->input('supported_modes');
            $valid = array_values(array_intersect($modes, ConsultationMode::values()));

            if (count($valid) !== count($modes)) {
                $validator->errors()->add('supported_modes', __('admin.settings.invalid_modes'));

                return;
            }

            if ($valid === []) {
                $validator->errors()->add('supported_modes', __('admin.settings.at_least_one_mode'));
            }
        });
    }
}
