<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Appointment;

use App\Domain\Appointment\Entity\Appointment;
use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Id\AppointmentId;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\Phone;
use App\Tests\Helper\ApiTestCase;
use DateTimeImmutable;

final class TherapistAppointmentControllerTest extends ApiTestCase
{
    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    private function createTestAppointment(string $status = 'REQUESTED'): Appointment
    {
        $appointment = Appointment::request(
            id: AppointmentId::generate(),
            timeSlot: TimeSlot::create(new DateTimeImmutable('+1 day 10:00'), 50),
            modality: AppointmentModality::ONLINE,
            fullName: 'Test Patient',
            email: Email::fromString('patient@test.com'),
            phone: Phone::fromString('+1234567890'),
            city: 'New York',
            country: 'USA',
            now: new DateTimeImmutable(),
        );

        if ($status === 'CONFIRMED') {
            $appointment->confirm(new DateTimeImmutable());
        }

        $repo = self::getContainer()->get(AppointmentRepositoryInterface::class);
        $repo->save($appointment);

        return $appointment;
    }

    // ── List appointments ─────────────────────────────────────────────

    public function testListAllAppointments(): void
    {
        $this->createTestAppointment();

        $this->jsonRequest('GET', '/api/therapist/appointments', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('appointments', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertGreaterThanOrEqual(1, $data['data']['pagination']['total']);
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(20, $data['data']['pagination']['limit']);
    }

    public function testListAppointmentsByStatus(): void
    {
        $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('GET', '/api/therapist/appointments?status=REQUESTED', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, $data['data']['pagination']['total']);

        foreach ($data['data']['appointments'] as $appointment) {
            $this->assertSame('REQUESTED', $appointment['status']);
        }
    }

    public function testListAppointmentsWithPaginationParams(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createTestAppointment();
        }

        $this->jsonRequest('GET', '/api/therapist/appointments?page=1&limit=2', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(2, $data['data']['pagination']['limit']);
        $this->assertCount(2, $data['data']['appointments']);
        $this->assertGreaterThanOrEqual(3, $data['data']['pagination']['total']);
    }

    public function testListAppointmentsPaginationWithStatusFilter(): void
    {
        $this->createTestAppointment('REQUESTED');
        $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('GET', '/api/therapist/appointments?status=REQUESTED&page=1&limit=1', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertCount(1, $data['data']['appointments']);
        $this->assertSame(1, $data['data']['pagination']['limit']);
        $this->assertGreaterThanOrEqual(2, $data['data']['pagination']['total']);
    }

    public function testListAppointmentsLimitCappedAt100(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments?limit=200', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(100, $data['data']['pagination']['limit']);
    }

    public function testListAppointmentsWithInvalidStatus(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments?status=INVALID', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testListAppointmentsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments');

        $this->assertResponseStatusCodeSame(401);
    }

    // ── Show appointment ──────────────────────────────────────────────

    public function testShowAppointment(): void
    {
        $appointment = $this->createTestAppointment();

        $this->jsonRequest('GET', '/api/therapist/appointments/' . $appointment->getId()->getValue(), [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame($appointment->getId()->getValue(), $data['data']['appointment']['id']);
        $this->assertSame('Test Patient', $data['data']['appointment']['full_name']);
        $this->assertArrayHasKey('payment_verified', $data['data']['appointment']);
        $this->assertArrayHasKey('updated_at', $data['data']['appointment']);
    }

    public function testShowNonExistentAppointment(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments/' . AppointmentId::generate()->getValue(), [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }

    // ── Confirm appointment ───────────────────────────────────────────

    public function testConfirmAppointment(): void
    {
        $appointment = $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/confirm', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('CONFIRMED', $data['data']['appointment']['status']);
        $this->assertSame('Appointment confirmed successfully.', $data['data']['message']);
    }

    public function testConfirmAlreadyConfirmedAppointment(): void
    {
        $appointment = $this->createTestAppointment('CONFIRMED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/confirm', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testConfirmNonExistentAppointment(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments/' . AppointmentId::generate()->getValue() . '/confirm', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }

    // ── Complete appointment ──────────────────────────────────────────

    public function testCompleteAppointment(): void
    {
        $appointment = $this->createTestAppointment('CONFIRMED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/complete', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('COMPLETED', $data['data']['appointment']['status']);
    }

    public function testCompleteRequestedAppointment(): void
    {
        $appointment = $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/complete', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(409);
    }

    // ── Cancel appointment ────────────────────────────────────────────

    public function testCancelAppointment(): void
    {
        $appointment = $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/cancel', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('CANCELLED', $data['data']['appointment']['status']);
    }

    // ── Book appointment ──────────────────────────────────────────────

    public function testBookAppointment(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments', [
            'slot_start_time' => '2026-06-01T10:00:00',
            'modality' => 'ONLINE',
            'full_name' => 'Walk-in Patient',
            'phone' => '+1234567890',
            'email' => 'walkin@example.com',
            'city' => 'Miami',
            'country' => 'USA',
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('CONFIRMED', $data['data']['appointment']['status']);
        $this->assertSame('Walk-in Patient', $data['data']['appointment']['full_name']);
        $this->assertSame('Appointment booked successfully.', $data['data']['message']);
    }

    public function testBookAppointmentWithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments', [
            'modality' => 'ONLINE',
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testBookAppointmentWithInvalidModality(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments', [
            'slot_start_time' => '2026-06-01T10:00:00',
            'modality' => 'INVALID',
            'full_name' => 'Test',
            'phone' => '+1234567890',
            'email' => 'test@example.com',
            'city' => 'Miami',
            'country' => 'USA',
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
    }

    // ── Payment status ────────────────────────────────────────────────

    public function testUpdatePaymentStatus(): void
    {
        $appointment = $this->createTestAppointment();

        $this->jsonRequest('PATCH', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/payment', [
            'payment_verified' => true,
        ], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['appointment']['payment_verified']);
    }

    public function testUpdatePaymentStatusWithMissingField(): void
    {
        $appointment = $this->createTestAppointment();

        $this->jsonRequest('PATCH', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/payment', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdatePaymentStatusForNonExistentAppointment(): void
    {
        $this->jsonRequest('PATCH', '/api/therapist/appointments/' . AppointmentId::generate()->getValue() . '/payment', [
            'payment_verified' => true,
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }

    // ── Patient role cannot access ────────────────────────────────────

    public function testPatientCannotAccessTherapistAppointments(): void
    {
        $patientToken = $this->createPatientAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/appointments', [], $patientToken);

        $this->assertResponseStatusCodeSame(403);
    }
}
