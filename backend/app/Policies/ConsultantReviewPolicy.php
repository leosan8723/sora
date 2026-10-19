<?php

namespace App\Policies;

use App\Models\ConsultantReview;
use App\Models\User;

class ConsultantReviewPolicy
{
    public function update(User $user, ConsultantReview $review): bool
    {
        return $review->client_id === $user->id;
    }

    public function delete(User $user, ConsultantReview $review): bool
    {
        return $review->client_id === $user->id || $user->isAdmin();
    }
}
