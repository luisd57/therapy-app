<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\WireShapes;

final class ListScheduleExceptionsControllerTest extends ApiTestCase
{
    public function testListExceptionsReturns200(): void
    {
        // The seeded exception is dated June 2026. Pin now so it cannot rot into the past.
        $this->freezeClock('2026-05-01T00:00:00+00:00');
        $token = $this->createTherapistAndGetToken();
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-15T09:00:00-04:00',
            'end_date_time' => '2026-06-15T17:00:00-04:00',
            'reason' => 'Day off',
            'is_all_day' => false,
        ], $token);
        $this->assertResponseStatusCodeSame(201);

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
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['exceptions', 'count'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'exceptions')), 'data.exceptions is a list');
        $this->assertEqualsCanonicalizing(
            WireShapes::SCHEDULE_EXCEPTION,
            array_keys(Json::arrayAt($data, 'data', 'exceptions', 0)),
            'data.exceptions[0] keys',
        );
    }

    public function testListExceptionsUnauthenticatedReturns401(): void
    {
        $this->client->request('GET', '/api/therapist/schedule/exceptions?from=2026-06-01&to=2026-06-30');

        $this->assertResponseStatusCodeSame(401);
    }
}
