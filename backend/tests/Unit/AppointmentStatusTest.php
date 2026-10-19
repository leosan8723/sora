<?php

namespace Tests\Unit;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    public function test_only_pending_and_confirmed_are_active(): void
    {
        $this->assertTrue(AppointmentStatus::Pending->isActive());
        $this->assertTrue(AppointmentStatus::Confirmed->isActive());
        $this->assertFalse(AppointmentStatus::Completed->isActive());
        $this->assertFalse(AppointmentStatus::Cancelled->isActive());
    }

    public function test_pending_can_be_confirmed_or_cancelled(): void
    {
        $this->assertTrue(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Confirmed));
        $this->assertTrue(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Cancelled));
        $this->assertFalse(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Completed));
    }

    public function test_confirmed_can_be_completed_or_cancelled(): void
    {
        $this->assertTrue(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Completed));
        $this->assertTrue(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Cancelled));
        $this->assertFalse(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Pending));
    }

    public function test_terminal_statuses_are_final(): void
    {
        foreach (AppointmentStatus::cases() as $target) {
            $this->assertFalse(AppointmentStatus::Completed->canTransitionTo($target));
            $this->assertFalse(AppointmentStatus::Cancelled->canTransitionTo($target));
        }
    }
}
