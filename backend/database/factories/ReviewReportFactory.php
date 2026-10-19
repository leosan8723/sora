<?php

namespace Database\Factories;

use App\Models\ConsultantReview;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReport>
 */
class ReviewReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_id' => ConsultantReview::factory(),
            'reporter_id' => User::factory(),
            'reason' => fake()->sentence(10),
            'status' => ReviewReport::STATUS_OPEN,
        ];
    }
}
