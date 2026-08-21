<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
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
            now: new \DateTimeImmutable(),
        );
        $scheduleRepo->save($schedule);
    }

    // ── available-slots ──────────────────────────────────────────────────

    public function testAvailableSlotsReturns200WithValidParams(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday. The window is the client's own instants.
        $this->client->request(
            'GET',
            '/api/appointments/available-slots'
            . '?from=' . urlencode('2026-06-01T00:00:00-04:00')
            . '&to=' . urlencode('2026-06-02T00:00:00-04:00'),
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('2026-06-01T04:00:00+00:00', $data['data']['from']);
        $this->assertSame('America/Caracas', $data['data']['practice_timezone']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);

        // Flat list, and every instant is emitted in UTC regardless of the
        // offset the caller used.
        $this->assertArrayHasKey('slots', $data['data']);
        $this->assertSame('2026-06-01T12:00:00+00:00', $data['data']['slots'][0]['start_time']);
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
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday
        $this->client->request(
            'GET',
            '/api/appointments/available-slots'
            . '?from=' . urlencode('2026-06-01T00:00:00-04:00')
            . '&to=' . urlencode('2026-06-02T00:00:00-04:00')
            . '&modality=ONLINE',
        );

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
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('lock_token', $data['data']);
        $this->assertArrayHasKey('expires_at', $data['data']);
    }

    public function testLockSlotResponseEmitsUtcInstantsWhateverOffsetTheCallerSent(): void
    {
        $this->freezeClock('2026-05-30T09:00:00+00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData()['data'];

        // The handler parses the caller's string, so the lock carries their zone
        // until it is formatted. See ADR-0001.
        $this->assertSame('2026-06-01T13:00:00+00:00', $data['slot_start_time']);
        // End and expiry follow from configured duration and TTL, so pin the zone
        // rather than a value that moves when either is retuned.
        $this->assertStringEndsWith('+00:00', $data['slot_end_time']);
        $this->assertStringEndsWith('+00:00', $data['expires_at']);
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
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Try to lock the same slot again
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    // ── request ──────────────────────────────────────────────────────────

    public function testRequestAppointmentReturns201(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        // 09:30 Caracas (13:30 UTC) is an offered start: 08:00 plus a multiple of the
        // 30-minute increment, and a full session still fits before 18:00.
        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
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
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
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
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
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

    // ── requester timezone ───────────────────────────────────────────────

    public function testRequestAppointmentRecordsTheRequestersTimezone(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            // The same instant the browse response gave, expressed in the
            // requester's own offset - Madrid in summer is UTC+2.
            'slot_start_time' => '2026-06-01T15:30:00+02:00',
            'modality' => 'ONLINE',
            'full_name' => 'Ana Torres',
            'phone' => '+34600000000',
            'email' => 'ana@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $appointment = $this->getResponseData()['data']['appointment'];
        $this->assertSame('2026-06-01T13:30:00+00:00', $appointment['start_time']);
    }

    public function testRequestAppointmentRejectsAFixedOffsetAsTimezone(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
            'full_name' => 'Ana Torres',
            'phone' => '+34600000000',
            'email' => 'ana@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
            // Carries no daylight-saving rules, so it would be wrong half the year.
            'timezone' => '+02:00',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('timezone', $this->getResponseData()['error']['details']);
    }

    // ── instant contract ─────────────────────────────────────────────────

    /**
     * A datetime with no offset would have to be guessed against some zone.
     * Guessing is exactly the ambiguity this contract removes, so it is a 422
     * rather than a silent reinterpretation.
     */
    public function testLockSlotRejectsADatetimeWithoutAnOffset(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('slot_start_time', $data['error']['details']);
    }

    public function testRequestAppointmentRejectsADatetimeWithoutAnOffset(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('slot_start_time', $data['error']['details']);
    }

    public function testLockSlotAcceptsZuluAndOffsetFormsAsTheSameInstant(): void
    {
        $this->createTherapistWithSchedule();

        // 13:30 UTC and 09:30-04:00 are the same moment, so the second must
        // collide with the first rather than being treated as a different slot.
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T13:30:00Z',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(409);
    }

    /**
     * createFromFormat rolls 2026-02-31 forward into March instead of failing,
     * so the validator needs a round-trip check on top of it.
     */
    public function testLockSlotRejectsACalendarDateThatDoesNotExist(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-02-31T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(422);
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
        $this->assertArrayHasKey('slots', $data['data']);
        $this->assertSame('America/Caracas', $data['data']['practice_timezone']);
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
