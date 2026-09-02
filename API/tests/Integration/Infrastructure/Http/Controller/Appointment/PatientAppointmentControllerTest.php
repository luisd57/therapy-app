<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\SeedsTherapistSchedule;

final class PatientAppointmentControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures below are dated June 2026; pin now so they cannot rot into the past.
        $this->freezeClock('2026-05-30 09:00:00');
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

        // 2026-06-01 is a Monday; 09:30 Caracas is an offered start
        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
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
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
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
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
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
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
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
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRequestAppointmentReturns403WithTherapistToken(): void
    {
        $therapistToken = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/patient/appointments', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
        ], $therapistToken);

        $this->assertResponseStatusCodeSame(403);
    }
}
