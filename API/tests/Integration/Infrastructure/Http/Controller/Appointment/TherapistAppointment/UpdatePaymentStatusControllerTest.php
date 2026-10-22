<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAppointment;
use App\Tests\Helper\WireShapes;

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
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['appointment', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::APPOINTMENT,
            array_keys(Json::arrayAt($data, 'data', 'appointment')),
            'data.appointment keys',
        );
        $this->assertTrue(Json::at($data, 'data', 'appointment', 'payment_verified'));
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
