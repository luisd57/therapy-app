<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class UpdatePaymentStatusControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

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
}
