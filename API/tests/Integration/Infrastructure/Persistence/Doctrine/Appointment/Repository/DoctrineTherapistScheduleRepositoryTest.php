<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Id\UserId;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use DateTimeImmutable;

final class DoctrineTherapistScheduleRepositoryTest extends IntegrationTestCase
{
    private TherapistScheduleRepositoryInterface $repository;
    private UserRepositoryInterface $userRepository;
    private UserId $therapistId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(TherapistScheduleRepositoryInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);

        $therapist = DomainTestHelper::createTherapist();
        $this->therapistId = $therapist->getId();
        $this->userRepository->save($therapist);
    }

    public function testSaveAndFindById(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
            supportsOnline: true,
            supportsInPerson: true,
        );
        $this->repository->save($schedule);

        $found = $this->repository->findById($schedule->getId());

        $this->assertNotNull($found);
        $this->assertTrue($schedule->getId()->equals($found->getId()));
        $this->assertTrue($this->therapistId->equals($found->getTherapistId()));
        $this->assertSame(WeekDay::MONDAY, $found->getDayOfWeek());
        $this->assertSame('09:00', $found->getStartTime());
        $this->assertSame('12:00', $found->getEndTime());
        $this->assertTrue($found->isSupportsOnline());
        $this->assertTrue($found->isSupportsInPerson());
        $this->assertTrue($found->isActive());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $result = $this->repository->findById(ScheduleId::generate());
        $this->assertNull($result);
    }

    public function testFindActiveByTherapistReturnsOnlyActive(): void
    {
        $activeSchedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
        );
        $this->repository->save($activeSchedule);

        $inactiveSchedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::TUESDAY,
            startTime: '14:00',
            endTime: '17:00',
            now: new DateTimeImmutable(),
        );
        $inactiveSchedule->deactivate(new DateTimeImmutable());
        $this->repository->save($inactiveSchedule);

        $results = $this->repository->findActiveByTherapist($this->therapistId);

        $ids = $results->map(fn(TherapistSchedule $schedule) => $schedule->getId()->getValue())->toArray();
        $this->assertContains($activeSchedule->getId()->getValue(), $ids);
        $this->assertNotContains($inactiveSchedule->getId()->getValue(), $ids);
    }

    public function testFindActiveByTherapistAndDay(): void
    {
        $mondaySchedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::MONDAY,
            startTime: '09:00',
            endTime: '12:00',
            now: new DateTimeImmutable(),
        );
        $this->repository->save($mondaySchedule);

        $tuesdaySchedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::TUESDAY,
            startTime: '10:00',
            endTime: '13:00',
            now: new DateTimeImmutable(),
        );
        $this->repository->save($tuesdaySchedule);

        $results = $this->repository->findActiveByTherapistAndDay($this->therapistId, WeekDay::MONDAY);

        $ids = $results->map(fn(TherapistSchedule $schedule) => $schedule->getId()->getValue())->toArray();
        $this->assertContains($mondaySchedule->getId()->getValue(), $ids);
        $this->assertNotContains($tuesdaySchedule->getId()->getValue(), $ids);
    }

    public function testDeleteRemovesSchedule(): void
    {
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $this->therapistId,
            dayOfWeek: WeekDay::FRIDAY,
            startTime: '08:00',
            endTime: '11:00',
            now: new DateTimeImmutable(),
        );
        $this->repository->save($schedule);

        $this->repository->delete($schedule);

        $this->assertNull($this->repository->findById($schedule->getId()));
    }
}
