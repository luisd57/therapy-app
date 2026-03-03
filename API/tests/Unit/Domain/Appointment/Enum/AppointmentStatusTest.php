<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Appointment\Enum;

use App\Domain\Appointment\Enum\AppointmentStatus;
use PHPUnit\Framework\TestCase;

final class AppointmentStatusTest extends TestCase
{
    // --- canTransitionTo: valid transitions ---

    public function testRequestedCanTransitionToConfirmed(): void
    {
        $this->assertTrue(AppointmentStatus::REQUESTED->canTransitionTo(AppointmentStatus::CONFIRMED));
    }

    public function testRequestedCanTransitionToCancelled(): void
    {
        $this->assertTrue(AppointmentStatus::REQUESTED->canTransitionTo(AppointmentStatus::CANCELLED));
    }

    public function testConfirmedCanTransitionToCompleted(): void
    {
        $this->assertTrue(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::COMPLETED));
    }

    public function testConfirmedCanTransitionToCancelled(): void
    {
        $this->assertTrue(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::CANCELLED));
    }

    // --- canTransitionTo: invalid transitions ---

    public function testRequestedCannotTransitionToCompleted(): void
    {
        $this->assertFalse(AppointmentStatus::REQUESTED->canTransitionTo(AppointmentStatus::COMPLETED));
    }

    public function testRequestedCannotTransitionToRequested(): void
    {
        $this->assertFalse(AppointmentStatus::REQUESTED->canTransitionTo(AppointmentStatus::REQUESTED));
    }

    public function testConfirmedCannotTransitionToRequested(): void
    {
        $this->assertFalse(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::REQUESTED));
    }

    public function testConfirmedCannotTransitionToConfirmed(): void
    {
        $this->assertFalse(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::CONFIRMED));
    }

    public function testCompletedCannotTransitionToAnyStatus(): void
    {
        foreach (AppointmentStatus::cases() as $target) {
            $this->assertFalse(
                AppointmentStatus::COMPLETED->canTransitionTo($target),
                "COMPLETED should not be able to transition to {$target->value}",
            );
        }
    }

    public function testCancelledCannotTransitionToAnyStatus(): void
    {
        foreach (AppointmentStatus::cases() as $target) {
            $this->assertFalse(
                AppointmentStatus::CANCELLED->canTransitionTo($target),
                "CANCELLED should not be able to transition to {$target->value}",
            );
        }
    }

    // --- isTerminal ---

    public function testCompletedIsTerminal(): void
    {
        $this->assertTrue(AppointmentStatus::COMPLETED->isTerminal());
    }

    public function testCancelledIsTerminal(): void
    {
        $this->assertTrue(AppointmentStatus::CANCELLED->isTerminal());
    }

    public function testRequestedIsNotTerminal(): void
    {
        $this->assertFalse(AppointmentStatus::REQUESTED->isTerminal());
    }

    public function testConfirmedIsNotTerminal(): void
    {
        $this->assertFalse(AppointmentStatus::CONFIRMED->isTerminal());
    }

    // --- blocksSlot ---

    public function testRequestedDoesNotBlockSlot(): void
    {
        $this->assertFalse(AppointmentStatus::REQUESTED->blocksSlot());
    }

    public function testConfirmedBlocksSlot(): void
    {
        $this->assertTrue(AppointmentStatus::CONFIRMED->blocksSlot());
    }

    public function testCompletedDoesNotBlockSlot(): void
    {
        $this->assertFalse(AppointmentStatus::COMPLETED->blocksSlot());
    }

    public function testCancelledDoesNotBlockSlot(): void
    {
        $this->assertFalse(AppointmentStatus::CANCELLED->blocksSlot());
    }

    // --- getDisplayName ---

    public function testGetDisplayNameForAllStatuses(): void
    {
        $this->assertSame('Requested', AppointmentStatus::REQUESTED->getDisplayName());
        $this->assertSame('Confirmed', AppointmentStatus::CONFIRMED->getDisplayName());
        $this->assertSame('Completed', AppointmentStatus::COMPLETED->getDisplayName());
        $this->assertSame('Cancelled', AppointmentStatus::CANCELLED->getDisplayName());
    }
}
