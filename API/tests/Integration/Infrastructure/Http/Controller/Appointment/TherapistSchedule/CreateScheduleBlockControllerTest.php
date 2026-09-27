<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;

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
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['schedule', 'message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
        $this->assertEqualsCanonicalizing([
            'id', 'day_of_week', 'day_name', 'start_time', 'end_time',
            'supports_online', 'supports_in_person', 'is_active',
        ], array_keys(Json::arrayAt($data, 'data', 'schedule')), 'data.schedule keys');
    }

    public function testCreateScheduleReturns422WithMissingFields(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => [
                    'start_time' => 'Start time is required',
                    'end_time' => 'End time is required',
                ],
            ],
        ], $this->getResponseData());
    }

    public function testCreateScheduleRejectsAFractionalDayOfWeek(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 3.5,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['day_of_week' => 'Day of week must be between 1 (Monday) and 7 (Sunday)'],
            Json::at($this->getResponseData(), 'error', 'details'),
        );
    }

    public function testCreateScheduleRejectsAStringWhereABooleanIsExpected(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'supports_online' => 'false',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['supports_online' => 'Supports online must be true or false'],
            Json::at($this->getResponseData(), 'error', 'details'),
        );
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
