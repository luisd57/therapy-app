<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAppointment;
use App\Tests\Helper\WireShapes;

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
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['appointment', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::APPOINTMENT,
            array_keys(Json::arrayAt($data, 'data', 'appointment')),
            'data.appointment keys',
        );
        $this->assertSame('COMPLETED', Json::at($data, 'data', 'appointment', 'status'));
    }

    public function testCompleteRequestedAppointment(): void
    {
        $appointment = $this->createTestAppointment(AppointmentStatus::REQUESTED);

        $this->jsonRequest('POST', '/api/therapist/appointments/' . $appointment->getId()->getValue() . '/complete', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(409);
    }
}
