<?php

namespace App\Http\Requests\Discovery;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConsultantSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'consultation_type' => ['required', 'integer', 'exists:consultation_types,id'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];

        if ($this->filled('from') && $this->filled('to')) {
            $rules['to'][] = 'after_or_equal:from';
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->dateValue('from');
            $to = $this->dateValue('to');

            if ($from === null || $to === null) {
                return;
            }

            $maxDays = (int) config('booking.max_range_days', 31);

            if ($from->diffInDays($to) + 1 > $maxDays) {
                $validator->errors()->add('to', __('validation.custom.to.max_range', ['max' => $maxDays]));
            }
        });
    }

    private function dateValue(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value, config('booking.timezone'))->startOfDay();
    }
}
