<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class CompleteAppointmentControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    public function testCompleteAppointment(): void
    {
        $appointment = $this->createTestAppointment(AppointmentStatus::CONFIRMED);

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/complete', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('COMPLETED', $data['data']['appointment']['status']);
    }

    public function testCompleteRequestedAppointment(): void
    {
        $appointment = $this->createTestAppointment(AppointmentStatus::REQUESTED);

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/complete', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(409);
    }
}
