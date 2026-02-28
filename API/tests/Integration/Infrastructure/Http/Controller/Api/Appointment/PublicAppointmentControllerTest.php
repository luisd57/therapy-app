<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Appointment;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\ValueObject\WeekDay;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class PublicAppointmentControllerTest extends ApiTestCase
{
    private function createTherapistWithSchedule(): void
    {
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $scheduleRepo = self::getContainer()->get(TherapistScheduleRepositoryInterface::class);

        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        // Create a Monday schedule (day_of_week = 1), 08:00-18:00
        $schedule = TherapistSchedule::create(
            id: ScheduleId::generate(),
            therapistId: $therapist->getId(),
            dayOfWeek: WeekDay::MONDAY,
            startTime: '08:00',
            endTime: '18:00',
            supportsOnline: true,
            supportsInPerson: true,
        );
        $scheduleRepo->save($schedule);
    }

    // ── available-slots ──────────────────────────────────────────────────

    public function testAvailableSlotsReturns200WithValidParams(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday
        $this->client->request('GET', '/api/appointments/available-slots?from=2026-06-01&to=2026-06-01');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('2026-06-01', $data['data']['from']);
        $this->assertSame('2026-06-01', $data['data']['to']);
        $this->assertArrayHasKey('total_slots', $data['data']);
        $this->assertArrayHasKey('slots_by_date', $data['data']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);
    }

    public function testAvailableSlotsReturns422WithMissingFrom(): void
    {
        $this->client->request('GET', '/api/appointments/available-slots');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testAvailableSlotsReturns422WithInvalidDateFormat(): void
    {
        $this->client->request('GET', '/api/appointments/available-slots?from=not-a-date&to=also-bad');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testAvailableSlotsWithModalityFilter(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday
        $this->client->request('GET', '/api/appointments/available-slots?from=2026-06-01&to=2026-06-01&modality=ONLINE');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ONLINE', $data['data']['modality']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);
    }

    // ── lock-slot ────────────────────────────────────────────────────────

    public function testLockSlotReturns201WhenSlotAvailable(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01T09:00:00 is a Monday at 09:00, within the 08:00-18:00 schedule
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('lock_token', $data['data']);
        $this->assertArrayHasKey('expires_at', $data['data']);
    }

    public function testLockSlotReturns422WithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/appointments/lock-slot', []);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testLockSlotReturns409WhenSlotAlreadyLocked(): void
    {
        $this->createTherapistWithSchedule();

        // Lock the slot once
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Try to lock the same slot again
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    // ── request ──────────────────────────────────────────────────────────

    public function testRequestAppointmentReturns201(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01T09:40:00 is a valid 50-min slot (08:00, 08:50, 09:40, ...)
        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'New York',
            'country' => 'US',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('appointment', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    public function testRequestAppointmentReturns422WithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T10:00:00',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testRequestAppointmentReturns409WhenSlotNotAvailable(): void
    {
        // Create therapist but no schedule, so no slots are available
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T10:00:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'New York',
            'country' => 'US',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    // ── next-available-week ────────────────────────────────────────────

    public function testNextAvailableWeekReturns200WithSchedule(): void
    {
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['found']);
        $this->assertNotNull($data['data']['week_start']);
        $this->assertNotNull($data['data']['week_end']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);
        $this->assertArrayHasKey('slots_by_date', $data['data']);
    }

    public function testNextAvailableWeekReturnsFoundFalseWithNoSchedule(): void
    {
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['data']['found']);
        $this->assertNull($data['data']['week_start']);
        $this->assertSame(0, $data['data']['total_slots']);
    }

    public function testNextAvailableWeekWithModalityFilter(): void
    {
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week?modality=ONLINE');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ONLINE', $data['data']['modality']);
    }

    public function testNextAvailableWeekReturns422WithInvalidModality(): void
    {
        $this->client->request('GET', '/api/appointments/next-available-week?modality=INVALID');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }
}
