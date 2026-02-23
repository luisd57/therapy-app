<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Appointment;

use App\Tests\Helper\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class TherapistScheduleControllerTest extends ApiTestCase
{
    // ── Schedule CRUD ────────────────────────────────────────────────────

    public function testListSchedulesAuthenticatedReturns200(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/therapist/schedule', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('schedules', $data['data']);
        $this->assertArrayHasKey('count', $data['data']);
    }

    public function testListSchedulesUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('GET', '/api/therapist/schedule');

        $this->assertResponseStatusCodeSame(401);
    }

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

    // ── Schedule Exceptions ──────────────────────────────────────────────

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

    public function testAddExceptionReturns201(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-15T09:00:00',
            'end_date_time' => '2026-06-15T17:00:00',
            'reason' => 'Day off',
            'is_all_day' => false,
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('exception', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    public function testAddExceptionReturns422WithMissingFields(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'reason' => 'Day off',
        ], $token);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testRemoveExceptionReturns204(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Create an exception first
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-07-01T09:00:00',
            'end_date_time' => '2026-07-01T17:00:00',
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

    // ── Authentication ───────────────────────────────────────────────────

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

    public function testDeleteScheduleUnauthenticatedReturns401(): void
    {
        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/' . $fakeId);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListExceptionsUnauthenticatedReturns401(): void
    {
        $this->client->request('GET', '/api/therapist/schedule/exceptions?from=2026-06-01&to=2026-06-30');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAddExceptionUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-15T09:00:00',
            'end_date_time' => '2026-06-15T17:00:00',
            'reason' => 'Day off',
            'is_all_day' => false,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRemoveExceptionUnauthenticatedReturns401(): void
    {
        $fakeId = Uuid::v7()->toRfc4122();

        $this->jsonRequest('DELETE', '/api/therapist/schedule/exceptions/' . $fakeId);

        $this->assertResponseStatusCodeSame(401);
    }
}
