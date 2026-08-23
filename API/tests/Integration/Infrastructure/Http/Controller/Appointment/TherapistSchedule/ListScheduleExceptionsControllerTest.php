<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;

final class ListScheduleExceptionsControllerTest extends ApiTestCase
{
    public function testListExceptionsReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->client->request(
            'GET',
            '/api/therapist/schedule/exceptions?from=2026-06-01&to=2026-06-30',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('exceptions', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
    }

    public function testListExceptionsUnauthenticatedReturns401(): void
    {
        $this->client->request('GET', '/api/therapist/schedule/exceptions?from=2026-06-01&to=2026-06-30');

        $this->assertResponseStatusCodeSame(401);
    }
}
