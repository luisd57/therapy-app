<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class CancelAppointmentControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    public function testCancelAppointment(): void
    {
        $appointment = $this->createTestAppointment('REQUESTED');

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/cancel', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('CANCELLED', $data['data']['appointment']['status']);
    }
}
