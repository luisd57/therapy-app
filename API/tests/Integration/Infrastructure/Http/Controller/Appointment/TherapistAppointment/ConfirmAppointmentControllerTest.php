<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAppointment;
use App\Tests\Helper\WireShapes;

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
        $appointment = $this->createTestAppointment(AppointmentStatus::REQUESTED);

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/confirm', [], $this->therapistToken);

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
        $this->assertSame('CONFIRMED', Json::at($data, 'data', 'appointment', 'status'));
        $this->assertSame('Appointment confirmed successfully.', Json::at($data, 'data', 'message'));
    }

    public function testConfirmAlreadyConfirmedAppointment(): void
    {
        $appointment = $this->createTestAppointment(AppointmentStatus::CONFIRMED);

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/confirm', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testConfirmNonExistentAppointment(): void
    {
        $this->jsonRequest('POST', '/api/therapist/appointments/' . AppointmentId::generate()->getValue() . '/confirm', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
    }
}
