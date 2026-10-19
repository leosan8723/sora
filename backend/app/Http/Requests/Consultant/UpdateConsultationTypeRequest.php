<?php

namespace App\Http\Requests\Consultant;

use App\Support\ConsultationModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultationTypeRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:600'],
            'price_toman' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000000'],
            'price_usd_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'mode' => ['sometimes', 'string', Rule::in(ConsultationModes::all())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
