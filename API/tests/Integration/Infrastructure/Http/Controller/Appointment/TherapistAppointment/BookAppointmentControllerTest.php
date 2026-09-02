<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Tests\Helper\ApiTestCase;

final class BookAppointmentControllerTest extends ApiTestCase
{
    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures below are dated June 2026. Pin now so they cannot rot into the past.
        $this->freezeClock('2026-05-30 09:00:00');
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    public function testBookAppointment(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments', [
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
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

    public function testBookingForAnUnknownPatientReturns404(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments', [
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
            'modality' => 'ONLINE',
            'full_name' => 'Walk-in Patient',
            'phone' => '+1234567890',
            'email' => 'walkin@example.com',
            'city' => 'Miami',
            'country' => 'USA',
            'patient_id' => '019525f3-5be1-7190-a6e1-aaa0000000ff',
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
        $this->assertFalse($this->getResponseData()['success']);
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
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
            'modality' => 'INVALID',
            'full_name' => 'Test',
            'phone' => '+1234567890',
            'email' => 'test@example.com',
            'city' => 'Miami',
            'country' => 'USA',
        ], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
    }
}
