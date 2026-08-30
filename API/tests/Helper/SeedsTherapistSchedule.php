<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;

/**
 * Persists the therapist and weekly schedule the appointment controller tests share.
 *
 * Monday 08:00-18:00 supporting both modalities, so slot queries have something to return.
 */
trait SeedsTherapistSchedule
{
    protected function createTherapistWithSchedule(): void
    {
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $scheduleRepo = self::getContainer()->get(TherapistScheduleRepositoryInterface::class);

        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        // Create a Monday schedule (day_of_week = 1), 08:00-18:00
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapist: $therapist,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '08:00',
            endTime: '18:00',
            supportsOnline: true,
            supportsInPerson: true,
            now: new \DateTimeImmutable(),
        );
        $scheduleRepo->save($schedule);
    }
}
