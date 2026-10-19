<?php

namespace App\Http\Requests\Consultant;

use App\Models\AvailabilityRule;

class UpdateAvailabilityRuleRequest extends StoreAvailabilityRuleRequest
{
    protected function ignoredRuleId(): ?int
    {
        $rule = $this->route('availabilityRule');

        return $rule instanceof AvailabilityRule ? $rule->id : null;
    }
}
