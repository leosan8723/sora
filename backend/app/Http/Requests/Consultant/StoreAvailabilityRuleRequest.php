<?php

namespace App\Http\Requests\Consultant;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityRuleRequest extends FormRequest
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
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('is_active', true) === false) {
                return;
            }

            $profile = $this->user()->consultantProfile()->first();

            if ($profile === null) {
                return;
            }

            $overlaps = $this->ignoredRuleId() === null
                ? $profile->availabilityRules()
                : $profile->availabilityRules()->whereKeyNot($this->ignoredRuleId());

            $exists = $overlaps
                ->where('weekday', (int) $this->input('weekday'))
                ->where('is_active', true)
                ->where('start_time', '<', (string) $this->input('end_time'))
                ->where('end_time', '>', (string) $this->input('start_time'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('start_time', __('validation.custom.start_time.overlap'));
            }
        });
    }

    /**
     * The rule being updated, so an update does not collide with itself.
     */
    protected function ignoredRuleId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        if (! array_key_exists('is_active', $validated)) {
            $validated['is_active'] = true;
        }

        return $key === null ? $validated : ($validated[$key] ?? $default);
    }
}
