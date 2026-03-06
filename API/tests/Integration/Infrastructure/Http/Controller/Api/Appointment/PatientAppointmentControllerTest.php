<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Appointment;

use App\Domain\Appointment\Entity\TherapistSchedule;
use App\Domain\Appointment\Repository\TherapistScheduleRepositoryInterface;
use App\Domain\Appointment\Id\ScheduleId;
use App\Domain\Appointment\Enum\WeekDay;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class PatientAppointmentControllerTest extends ApiTestCase
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

    private function createPatientWithProfileAndGetToken(): string
    {
        $token = $this->createPatientAndGetToken();

        $this->jsonRequest('PUT', '/api/patient/profile', [
            'phone' => '+1234567890',
            'address' => [
                'street' => '123 Test St',
                'city' => 'New York',
                'country' => 'US',
            ],
        ], $token);

        $this->assertResponseIsSuccessful();

        return $token;
    }

    // ── Success ───────────────────────────────────────────────────────

    public function testRequestAppointmentReturns201(): void
    {
        $this->createTherapistWithSchedule();
        $patientToken = $this->createPatientWithProfileAndGetToken();

        // 2026-06-01 is a Monday, 09:40 is a valid 50-min slot
        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'ONLINE',
        ], $patientToken);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('appointment', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
        $this->assertSame('REQUESTED', $data['data']['appointment']['status']);
        $this->assertSame('ONLINE', $data['data']['appointment']['modality']);
        $this->assertArrayHasKey('patient_id', $data['data']['appointment']);
        $this->assertNotNull($data['data']['appointment']['patient_id']);
    }

    // ── Validation errors ─────────────────────────────────────────────

    public function testRequestAppointmentReturns422WithMissingFields(): void
    {
        $patientToken = $this->createPatientWithProfileAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [], $patientToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testRequestAppointmentReturns422WithInvalidModality(): void
    {
        $patientToken = $this->createPatientWithProfileAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'INVALID',
        ], $patientToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    // ── Incomplete profile ────────────────────────────────────────────

    public function testRequestAppointmentReturns422WithIncompleteProfile(): void
    {
        $this->createTherapistWithSchedule();
        // Patient without profile update (no phone, no address)
        $patientToken = $this->createPatientAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'ONLINE',
        ], $patientToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('INCOMPLETE_PROFILE', $data['error']['code']);
    }

    // ── Slot not available ────────────────────────────────────────────

    public function testRequestAppointmentReturns409WhenSlotNotAvailable(): void
    {
        // Create therapist but no schedule, so no slots are available
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        $patientToken = $this->createPatientWithProfileAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T10:00:00',
            'modality' => 'ONLINE',
        ], $patientToken);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    // ── Authentication ────────────────────────────────────────────────

    public function testRequestAppointmentReturns401WithoutToken(): void
    {
        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRequestAppointmentReturns403WithTherapistToken(): void
    {
        $therapistToken = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:40:00',
            'modality' => 'ONLINE',
        ], $therapistToken);

        $this->assertResponseStatusCodeSame(403);
    }
}
