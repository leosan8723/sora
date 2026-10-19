<?php

namespace App\Http\Requests\Consultant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultantProfileRequest extends FormRequest
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
            'headline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:160'],
            'headline_en' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bio_en' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'years_experience' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
        ];
    }
}
