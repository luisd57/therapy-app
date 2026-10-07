<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Tests\Helper\IntegrationTestCase;
use App\Tests\Helper\UsesUtcInstants;
use DateTimeImmutable;
use DateTimeZone;

final class DoctrineAppointmentRepositoryTest extends IntegrationTestCase
{
    use UsesUtcInstants;

    private AppointmentRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(AppointmentRepositoryInterface::class);
    }

    private function createAppointment(
        ?AppointmentId $id = null,
        ?DateTimeImmutable $startTime = null,
        string $fullName = 'John Doe',
        string $email = 'john@test.com',
    ): Appointment {
        return Appointment::request(
            id: $id ?? AppointmentId::generate(),
            timeSlot: TimeSlot::create($startTime ?? self::utc('2026-06-02 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: $fullName,
            email: Email::fromString($email),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'US',
            now: new DateTimeImmutable(),
        );
    }

    public function testSaveAndFindById(): void
    {
        $appointment = $this->createAppointment();
        $this->repository->save($appointment);

        $found = $this->repository->findById($appointment->getId());

        $this->assertNotNull($found);
        $this->assertTrue($appointment->getId()->equals($found->getId()));
        $this->assertSame('John Doe', $found->getFullName());
        $this->assertSame('john@test.com', $found->getEmail()->getValue());
        $this->assertSame(AppointmentStatus::REQUESTED, $found->getStatus());
        $this->assertSame(AppointmentModality::ONLINE, $found->getModality());
        $this->assertSame('New York', $found->getCity());
        $this->assertSame('US', $found->getCountry());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $result = $this->repository->findById(AppointmentId::generate());
        $this->assertNull($result);
    }

    public function testFindBlockingByDateRangeReturnsRequestedAndConfirmed(): void
    {
        $requested = $this->createAppointment(
            startTime: self::utc('2026-06-02 09:00:00'),
            email: 'requested@test.com',
        );
        $this->repository->save($requested);

        $confirmed = $this->createAppointment(
            startTime: self::utc('2026-06-02 10:00:00'),
            email: 'confirmed@test.com',
        );
        $confirmed->confirm(new DateTimeImmutable());
        $this->repository->save($confirmed);

        $completed = $this->createAppointment(
            startTime: self::utc('2026-06-02 11:00:00'),
            email: 'completed@test.com',
        );
        $completed->confirm(new DateTimeImmutable());
        $completed->complete(new DateTimeImmutable());
        $this->repository->save($completed);

        $cancelled = $this->createAppointment(
            startTime: self::utc('2026-06-02 12:00:00'),
            email: 'cancelled@test.com',
        );
        $cancelled->cancel(new DateTimeImmutable());
        $this->repository->save($cancelled);

        $results = $this->repository->findBlockingByDateRange(
            self::utc('2026-06-02 00:00:00'),
            self::utc('2026-06-02 23:59:59'),
        );

        $ids = $results->map(fn(Appointment $appointment) => $appointment->getId()->getValue())->toArray();
        $this->assertContains($requested->getId()->getValue(), $ids);
        $this->assertContains($confirmed->getId()->getValue(), $ids);
        $this->assertNotContains($completed->getId()->getValue(), $ids);
        $this->assertNotContains($cancelled->getId()->getValue(), $ids);
    }

    public function testFindByStatus(): void
    {
        $requested = $this->createAppointment(
            startTime: self::utc('2026-07-01 09:00:00'),
            email: 'status-req@test.com',
        );
        $this->repository->save($requested);

        $confirmed = $this->createAppointment(
            startTime: self::utc('2026-07-01 10:00:00'),
            email: 'status-conf@test.com',
        );
        $confirmed->confirm(new DateTimeImmutable());
        $this->repository->save($confirmed);

        $results = $this->repository->findByStatus(AppointmentStatus::REQUESTED);

        $ids = $results->map(fn(Appointment $appointment) => $appointment->getId()->getValue())->toArray();
        $this->assertContains($requested->getId()->getValue(), $ids);
        $this->assertNotContains($confirmed->getId()->getValue(), $ids);
    }

    public function testFindConfirmedByDateReturnsOnlyConfirmedForGivenDate(): void
    {
        // The day is the caller's, so its edges are practice-local midnights, not UTC ones.
        $targetDate = new DateTimeImmutable('2026-09-15T00:00:00-04:00');

        // Confirmed on target date - should be included
        $confirmedOnDate = $this->createAppointment(
            startTime: self::utc('2026-09-15 09:00:00'),
            email: 'confirmed-on-date@test.com',
        );
        $confirmedOnDate->confirm(new DateTimeImmutable());
        $this->repository->save($confirmedOnDate);

        // Late evening in the practice zone, already the 16th in UTC - should be included
        $confirmedOnDate2 = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-09-15T22:00:00-04:00'),
            email: 'confirmed-on-date2@test.com',
        );
        $confirmedOnDate2->confirm(new DateTimeImmutable());
        $this->repository->save($confirmedOnDate2);

        // Requested on target date - should NOT be included
        $requestedOnDate = $this->createAppointment(
            startTime: self::utc('2026-09-15 11:00:00'),
            email: 'requested-on-date@test.com',
        );
        $this->repository->save($requestedOnDate);

        // The evening before in the practice zone, already the 15th in UTC - should NOT be included
        $confirmedOtherDate = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-09-14T23:00:00-04:00'),
            email: 'confirmed-other-date@test.com',
        );
        $confirmedOtherDate->confirm(new DateTimeImmutable());
        $this->repository->save($confirmedOtherDate);

        $results = $this->repository->findConfirmedByDate($targetDate);

        $ids = $results->map(fn(Appointment $appointment) => $appointment->getId()->getValue())->toArray();
        $this->assertCount(2, $results);
        $this->assertContains($confirmedOnDate->getId()->getValue(), $ids);
        $this->assertContains($confirmedOnDate2->getId()->getValue(), $ids);
        $this->assertNotContains($requestedOnDate->getId()->getValue(), $ids);
        $this->assertNotContains($confirmedOtherDate->getId()->getValue(), $ids);
    }

    public function testFindConfirmedByDateOrdersByStartTimeAsc(): void
    {
        $targetDate = new DateTimeImmutable('2026-09-20T00:00:00-04:00');

        $later = $this->createAppointment(
            startTime: self::utc('2026-09-20 15:00:00'),
            email: 'later@test.com',
        );
        $later->confirm(new DateTimeImmutable());
        $this->repository->save($later);

        $earlier = $this->createAppointment(
            startTime: self::utc('2026-09-20 08:00:00'),
            email: 'earlier@test.com',
        );
        $earlier->confirm(new DateTimeImmutable());
        $this->repository->save($earlier);

        $results = $this->repository->findConfirmedByDate($targetDate);

        $this->assertSame([$earlier, $later], $results->toArray());
    }

    public function testFindConfirmedByDateReturnsEmptyCollectionWhenNone(): void
    {
        $results = $this->repository->findConfirmedByDate(self::utc('2030-01-01'));

        $this->assertCount(0, $results);
    }

    /**
     * The DB column carries no offset of its own, so an instant written from a
     * non-UTC DateTimeImmutable must still come back as the same point on the
     * timeline. Without a UTC-normalising DBAL type the offset is dropped on
     * write and the value silently shifts by the writer's offset.
     */
    public function testPersistsAndReadsBackTheSameInstantInUtc(): void
    {
        $startTime = new DateTimeImmutable('2026-06-01 09:00:00', new DateTimeZone('America/Caracas'));

        $appointment = $this->createAppointment(
            startTime: $startTime,
            email: 'utc-roundtrip@test.com',
        );
        $this->repository->save($appointment);
        $this->entityManager->clear();

        $found = $this->repository->findById($appointment->getId());

        $this->assertNotNull($found);
        $this->assertSame(
            $startTime->getTimestamp(),
            $found->getTimeSlot()->getStartTime()->getTimestamp(),
            'Stored instant shifted; the written offset was discarded.',
        );
        $this->assertSame('+00:00', $found->getTimeSlot()->getStartTime()->format('P'));
    }

    /**
     * Doctrine's ParameterTypeInferer binds a bare DateTimeImmutable through the
     * naive datetime type, writing a literal without an offset. A range whose
     * bounds are expressed in the practice zone must still match a row written
     * in UTC - this is the cheapest detector for a missing setParameter() type.
     */
    public function testDateRangeQueryMatchesInstantsWrittenWithADifferentOffset(): void
    {
        $caracas = new DateTimeZone('America/Caracas');

        $appointment = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-06-01 13:00:00', new DateTimeZone('UTC')),
            email: 'offset-range@test.com',
        );
        $appointment->confirm(new DateTimeImmutable('2026-05-01 00:00:00', new DateTimeZone('UTC')));
        $this->repository->save($appointment);
        $this->entityManager->clear();

        // 08:00-10:00 in Caracas is 12:00-14:00 UTC, so the 13:00 UTC row is inside.
        $results = $this->repository->findConfirmedByDateRange(
            new DateTimeImmutable('2026-06-01 08:00:00', $caracas),
            new DateTimeImmutable('2026-06-01 10:00:00', $caracas),
        );

        $ids = $results->map(fn(Appointment $found) => $found->getId()->getValue())->toArray();
        $this->assertContains($appointment->getId()->getValue(), $ids);
    }

    public function testDeleteRemovesAppointment(): void
    {
        $appointment = $this->createAppointment(
            startTime: self::utc('2026-08-01 09:00:00'),
            email: 'delete@test.com',
        );
        $this->repository->save($appointment);

        $this->repository->delete($appointment);

        $this->assertNull($this->repository->findById($appointment->getId()));
    }
}
