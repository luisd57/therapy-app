<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Id\AppointmentId;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsAppointment;
use App\Tests\Helper\WireShapes;

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
        $this->assertSame($appointment->getId()->getValue(), Json::at($data, 'data', 'appointment', 'id'));
        $this->assertSame('Test Patient', Json::at($data, 'data', 'appointment', 'full_name'));
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['appointment'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::APPOINTMENT,
            array_keys(Json::arrayAt($data, 'data', 'appointment')),
            'data.appointment keys',
        );
    }

    public function testShowNonExistentAppointment(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments/' . AppointmentId::generate()->getValue(), [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getResponseData();
        $this->assertEqualsCanonicalizing(WireShapes::ERROR_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(WireShapes::ERROR, array_keys(Json::arrayAt($data, 'error')), 'error keys');
        $this->assertFalse($data['success']);
        $this->assertSame('NOT_FOUND', Json::at($data, 'error', 'code'));
    }
}
