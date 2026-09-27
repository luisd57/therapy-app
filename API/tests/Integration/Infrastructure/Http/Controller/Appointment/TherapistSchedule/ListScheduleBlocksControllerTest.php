<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;

final class ListScheduleBlocksControllerTest extends ApiTestCase
{
    public function testListSchedulesAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/schedule', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('schedules', Json::arrayAt($data, 'data'));
        $this->assertArrayHasKey('count', Json::arrayAt($data, 'data'));
    }

    public function testListSchedulesUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/schedule');

        $this->assertResponseStatusCodeSame(401);
    }
}
