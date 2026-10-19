<?php

namespace App\Http\Requests\Consultant;

use Illuminate\Foundation\Http\FormRequest;

class SyncSpecialtiesRequest extends FormRequest
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
            'specialties' => ['present', 'array', 'max:20'],
            'specialties.*' => ['integer', 'distinct', 'exists:specialties,id'],
        ];
    }
}
