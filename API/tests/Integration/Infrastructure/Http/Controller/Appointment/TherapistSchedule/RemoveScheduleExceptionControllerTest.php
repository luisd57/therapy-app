<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class RemoveScheduleExceptionControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The fixture below is dated July 2026. Pin now so it cannot rot into the past.
        $this->freezeClock('2026-05-01T00:00:00+00:00');
    }

    public function testRemoveExceptionReturns204(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Create an exception first
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-07-01T09:00:00-04:00',
            'end_date_time' => '2026-07-01T17:00:00-04:00',
            'reason' => 'Holiday',
            'is_all_day' => false,
        ], $token);
        $this->assertResponseStatusCodeSame(201);
        $createData = $this->getResponseData();
        $exceptionId = $createData['data']['exception']['id'];

        // Delete it
        $this->jsonRequest('DELETE', '/api/therapist/schedule/exceptions/' . $exceptionId, [], $token);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testRemoveExceptionReturns404WhenNotFound(): void
    {
        $token = $this->createTherapistAndGetToken();

        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/exceptions/' . $fakeId, [], $token);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRemoveExceptionUnauthenticatedReturns401(): void
    {
        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/exceptions/' . $fakeId);

        $this->assertResponseStatusCodeSame(401);
    }
}
