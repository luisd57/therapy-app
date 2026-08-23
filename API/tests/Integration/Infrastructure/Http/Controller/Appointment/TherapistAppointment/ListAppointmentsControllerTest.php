<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Domain\Appointment\Enum\AppointmentStatus;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAppointment;

final class ListAppointmentsControllerTest extends ApiTestCase
{
    use SeedsAppointment;

    private string $therapistToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->therapistToken = $this->createTherapistAndGetToken();
    }

    public function testListAllAppointments(): void
    {
        $this->createTestAppointment();

        $this->jsonRequest('GET', '/api/therapist/appointments', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('appointments', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertGreaterThanOrEqual(1, $data['data']['pagination']['total']);
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(20, $data['data']['pagination']['limit']);
    }

    public function testListAppointmentsByStatus(): void
    {
        $this->createTestAppointment(AppointmentStatus::REQUESTED);

        $this->jsonRequest('GET', '/api/therapist/appointments?status=REQUESTED', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, $data['data']['pagination']['total']);

        foreach ($data['data']['appointments'] as $appointment) {
            $this->assertSame('REQUESTED', $appointment['status']);
        }
    }

    public function testListAppointmentsWithPaginationParams(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createTestAppointment();
        }

        $this->jsonRequest('GET', '/api/therapist/appointments?page=1&limit=2', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(1, $data['data']['pagination']['page']);
        $this->assertSame(2, $data['data']['pagination']['limit']);
        $this->assertCount(2, $data['data']['appointments']);
        $this->assertGreaterThanOrEqual(3, $data['data']['pagination']['total']);
    }

    public function testListAppointmentsPaginationWithStatusFilter(): void
    {
        $this->createTestAppointment(AppointmentStatus::REQUESTED);
        $this->createTestAppointment(AppointmentStatus::REQUESTED);

        $this->jsonRequest('GET', '/api/therapist/appointments?status=REQUESTED&page=1&limit=1', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertCount(1, $data['data']['appointments']);
        $this->assertSame(1, $data['data']['pagination']['limit']);
        $this->assertGreaterThanOrEqual(2, $data['data']['pagination']['total']);
    }

    public function testListAppointmentsLimitCappedAt100(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments?limit=200', [], $this->therapistToken);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame(100, $data['data']['pagination']['limit']);
    }

    public function testListAppointmentsWithInvalidStatus(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments?status=INVALID', [], $this->therapistToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testListAppointmentsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/therapist/appointments');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPatientCannotAccessTherapistAppointments(): void
    {
        $patientToken = $this->createPatientAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/appointments', [], $patientToken);

        $this->assertResponseStatusCodeSame(403);
    }
}
