<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class ConfirmAppointmentControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

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
}
