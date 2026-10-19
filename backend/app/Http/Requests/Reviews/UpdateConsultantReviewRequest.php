<?php

namespace App\Http\Requests\Reviews;

use App\Models\ConsultantReview;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A client may edit their own review. Edited reviews return to moderation
 * (see ConsultantReviewController::update) so approved text can never be
 * swapped for unreviewed content.
 */
class UpdateConsultantReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');

        if (! $review instanceof ConsultantReview) {
            return false;
        }

        return $this->user() !== null && $review->client_id === $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'rating' => __('reviews.attributes.rating'),
            'body' => __('reviews.attributes.body'),
        ];
    }
}
