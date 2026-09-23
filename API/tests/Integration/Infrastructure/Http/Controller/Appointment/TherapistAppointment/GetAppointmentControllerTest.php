<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class GetAppointmentControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    public function testShowAppointment(): void
    {
        $appointment = $this->createTestAppointment();

        $this->jsonRequest('GET', '/api/therapist/appointments/' . $appointment->getId()->getValue(), [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame($appointment->getId()->getValue(), $data['data']['appointment']['id']);
        $this->assertSame('Test Patient', $data['data']['appointment']['full_name']);
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['appointment'], array_keys($data['data']), 'data keys');
        $this->assertEqualsCanonicalizing([
            'id', 'start_time', 'end_time', 'modality', 'status',
            'full_name', 'email', 'phone', 'city', 'country',
            'patient_id', 'payment_verified', 'created_at', 'updated_at', 'requester_timezone',
        ], array_keys($data['data']['appointment']), 'data.appointment keys');
    }

    public function testShowNonExistentAppointment(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments/' . AppointmentId::generate()->getValue(), [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }
}
