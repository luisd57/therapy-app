<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Appointment\Repository;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentId;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\AppointmentStatus;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Tests\Helper\IntegrationTestCase;
use DateTimeImmutable;

final class DoctrineAppointmentRepositoryTest extends IntegrationTestCase
{
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
            timeSlot: TimeSlot::create($startTime ?? new DateTimeImmutable('2026-06-02 09:00:00'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: $fullName,
            email: Email::fromString($email),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'US',
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
            startTime: new DateTimeImmutable('2026-06-02 09:00:00'),
            email: 'requested@test.com',
        );
        $this->repository->save($requested);

        $confirmed = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-06-02 10:00:00'),
            email: 'confirmed@test.com',
        );
        $confirmed->confirm();
        $this->repository->save($confirmed);

        $completed = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-06-02 11:00:00'),
            email: 'completed@test.com',
        );
        $completed->confirm();
        $completed->complete();
        $this->repository->save($completed);

        $cancelled = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-06-02 12:00:00'),
            email: 'cancelled@test.com',
        );
        $cancelled->cancel();
        $this->repository->save($cancelled);

        $results = $this->repository->findBlockingByDateRange(
            new DateTimeImmutable('2026-06-02 00:00:00'),
            new DateTimeImmutable('2026-06-02 23:59:59'),
        );

        $ids = $results->map(fn(Appointment $a) => $a->getId()->getValue())->toArray();
        $this->assertContains($requested->getId()->getValue(), $ids);
        $this->assertContains($confirmed->getId()->getValue(), $ids);
        $this->assertNotContains($completed->getId()->getValue(), $ids);
        $this->assertNotContains($cancelled->getId()->getValue(), $ids);
    }

    public function testFindByStatus(): void
    {
        $requested = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-07-01 09:00:00'),
            email: 'status-req@test.com',
        );
        $this->repository->save($requested);

        $confirmed = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-07-01 10:00:00'),
            email: 'status-conf@test.com',
        );
        $confirmed->confirm();
        $this->repository->save($confirmed);

        $results = $this->repository->findByStatus(AppointmentStatus::REQUESTED);

        $ids = $results->map(fn(Appointment $a) => $a->getId()->getValue())->toArray();
        $this->assertContains($requested->getId()->getValue(), $ids);
        $this->assertNotContains($confirmed->getId()->getValue(), $ids);
    }

    public function testDeleteRemovesAppointment(): void
    {
        $appointment = $this->createAppointment(
            startTime: new DateTimeImmutable('2026-08-01 09:00:00'),
            email: 'delete@test.com',
        );
        $this->repository->save($appointment);

        $this->repository->delete($appointment);

        $this->assertNull($this->repository->findById($appointment->getId()));
    }
}
