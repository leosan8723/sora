<?php

namespace App\Http\Requests\Reviews;

use App\Enums\ReviewStatus;
use App\Models\ConsultantReview;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A report targets a published review the caller did not write, and each
 * user may report the same review once. These checks live in the validator
 * so every failure is a field-level 422 instead of a controller branch.
 */
class StoreReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $review = $this->route('review');

            if (! $review instanceof ConsultantReview) {
                return;
            }

            if ($review->status !== ReviewStatus::Approved) {
                $validator->errors()->add('review_id', __('reviews.not_approved'));

                return;
            }

            /** @var User|null $user */
            $user = $this->user();

            if ($user !== null && (int) $review->client_id === (int) $user->id) {
                $validator->errors()->add('review_id', __('reviews.own_review'));

                return;
            }

            if (
                $user !== null && ReviewReport::query()
                    ->where('review_id', $review->id)
                    ->where('reporter_id', $user->id)
                    ->exists()
            ) {
                $validator->errors()->add('review_id', __('reviews.already_reported'));
            }
        });
    }
}
