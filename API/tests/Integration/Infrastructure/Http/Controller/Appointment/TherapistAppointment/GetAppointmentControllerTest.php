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
        $this->assertArrayHasKey('payment_verified', $data['data']['appointment']);
        $this->assertArrayHasKey('updated_at', $data['data']['appointment']);
    }

    public function testShowNonExistentAppointment(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments/' . AppointmentId::generate()->getValue(), [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }
}
