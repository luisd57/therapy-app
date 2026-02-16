<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\ScheduleException;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\Appointment\ValueObject\ExceptionId;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use DateTimeImmutable;

final class DoctrineScheduleExceptionRepositoryTest extends IntegrationTestCase
{
    private ScheduleExceptionRepositoryInterface $repository;
    private UserRepositoryInterface $userRepository;
    private UserId $therapistId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ScheduleExceptionRepositoryInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);

        $therapist = DomainTestHelper::createTherapist();
        $this->therapistId = $therapist->getId();
        $this->userRepository->save($therapist);
    }

    public function testSaveAndFindById(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $this->therapistId,
            startDateTime: new DateTimeImmutable('2026-03-15 09:00:00'),
            endDateTime: new DateTimeImmutable('2026-03-15 17:00:00'),
            reason: 'Day off',
            isAllDay: false,
        );
        $this->repository->save($exception);

        $found = $this->repository->findById($exception->getId());

        $this->assertNotNull($found);
        $this->assertTrue($exception->getId()->equals($found->getId()));
        $this->assertTrue($this->therapistId->equals($found->getTherapistId()));
        $this->assertSame('Day off', $found->getReason());
        $this->assertFalse($found->isAllDay());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $result = $this->repository->findById(ExceptionId::generate());
        $this->assertNull($result);
    }

    public function testFindByTherapistAndDateRangeReturnsInRange(): void
    {
        $inRange = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $this->therapistId,
            startDateTime: new DateTimeImmutable('2026-04-10 09:00:00'),
            endDateTime: new DateTimeImmutable('2026-04-10 17:00:00'),
            reason: 'Conference',
        );
        $this->repository->save($inRange);

        $outOfRange = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $this->therapistId,
            startDateTime: new DateTimeImmutable('2026-06-15 09:00:00'),
            endDateTime: new DateTimeImmutable('2026-06-15 17:00:00'),
            reason: 'Vacation',
        );
        $this->repository->save($outOfRange);

        $results = $this->repository->findByTherapistAndDateRange(
            $this->therapistId,
            new DateTimeImmutable('2026-04-01 00:00:00'),
            new DateTimeImmutable('2026-04-30 23:59:59'),
        );

        $ids = $results->map(fn(ScheduleException $e) => $e->getId()->getValue())->toArray();
        $this->assertContains($inRange->getId()->getValue(), $ids);
        $this->assertNotContains($outOfRange->getId()->getValue(), $ids);
    }

    public function testFindByTherapistAndDateRangeExcludesOutOfRange(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $this->therapistId,
            startDateTime: new DateTimeImmutable('2026-07-01 09:00:00'),
            endDateTime: new DateTimeImmutable('2026-07-01 17:00:00'),
            reason: 'Holiday',
        );
        $this->repository->save($exception);

        $results = $this->repository->findByTherapistAndDateRange(
            $this->therapistId,
            new DateTimeImmutable('2026-03-01 00:00:00'),
            new DateTimeImmutable('2026-03-31 23:59:59'),
        );

        $this->assertCount(0, $results);
    }

    public function testDeleteRemovesException(): void
    {
        $exception = ScheduleException::create(
            id: ExceptionId::generate(),
            therapistId: $this->therapistId,
            startDateTime: new DateTimeImmutable('2026-05-20 08:00:00'),
            endDateTime: new DateTimeImmutable('2026-05-20 16:00:00'),
            reason: 'Personal',
        );
        $this->repository->save($exception);

        $this->repository->delete($exception);

        $this->assertNull($this->repository->findById($exception->getId()));
    }
}
