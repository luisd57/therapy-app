<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class DeleteScheduleBlockControllerTest extends ApiTestCase
{
    public function testDeleteScheduleReturns204(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Create a schedule first
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 3,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);
        $this->assertResponseStatusCodeSame(201);
        $createData = $this->getResponseData();
        $scheduleId = $createData['data']['schedule']['id'];

        // Delete it
        $this->jsonRequest('DELETE', '/api/therapist/schedule/' . $scheduleId, [], $token);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteScheduleReturns404WhenNotFound(): void
    {
        $token = $this->createTherapistAndGetToken();

        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/' . $fakeId, [], $token);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteScheduleUnauthenticatedReturns401(): void
    {
        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/' . $fakeId);

        $this->assertResponseStatusCodeSame(401);
    }
}
