<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;

final class CreateScheduleBlockControllerTest extends ApiTestCase
{
    public function testCreateScheduleReturns201(): void
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
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('schedule', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    public function testCreateScheduleReturns422WithMissingFields(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testCreateScheduleReturns409WhenOverlapping(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Create first schedule block
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);
        $this->assertResponseStatusCodeSame(201);

        // Create overlapping schedule block on the same day
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
            'start_time' => '11:00',
            'end_time' => '14:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ], $token);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testCreateScheduleUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => true,
            'supports_in_person' => true,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
