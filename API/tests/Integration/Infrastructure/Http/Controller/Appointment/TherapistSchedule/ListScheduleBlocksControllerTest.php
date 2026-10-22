<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\WireShapes;

final class ListScheduleBlocksControllerTest extends ApiTestCase
{
    public function testListSchedulesAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('GET', '/api/therapist/schedule', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['schedules', 'count'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'schedules')), 'data.schedules is a list');
        $this->assertEqualsCanonicalizing(
            WireShapes::SCHEDULE_BLOCK,
            array_keys(Json::arrayAt($data, 'data', 'schedules', 0)),
            'data.schedules[0] keys',
        );
    }

    public function testListSchedulesUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/schedule');

        $this->assertResponseStatusCodeSame(401);
    }
}
