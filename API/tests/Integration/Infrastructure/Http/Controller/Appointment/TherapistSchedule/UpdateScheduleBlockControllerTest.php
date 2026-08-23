<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateScheduleBlockControllerTest extends ApiTestCase
{
    public function testUpdateScheduleReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Create a schedule first
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);
        $this->assertResponseStatusCodeSame(201);
        $createData = $this->getResponseData();
        $scheduleId = $createData['data']['schedule']['id'];

        // Update it
        $this->jsonRequest('PUT', '/api/therapist/schedule/' . $scheduleId, [
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'supports_online' => false,
            'supports_in_person' => true,
        ], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('schedule', $data['data']);
        $this->assertSame('10:00', $data['data']['schedule']['start_time']);
        $this->assertSame('13:00', $data['data']['schedule']['end_time']);
    }

    public function testUpdateScheduleReturns404WhenNotFound(): void
    {
        $token = $this->createTherapistAndGetToken();

        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('PUT', '/api/therapist/schedule/' . $fakeId, [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateScheduleUnauthenticatedReturns401(): void
    {
        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('PUT', '/api/therapist/schedule/' . $fakeId, [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
