<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Tests\Helper\ApiTestCase;

final class AddScheduleExceptionControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures below are dated June 2026; pin now so they cannot rot into the past.
        // testAddNonAllDayException... asserts created_at against this exact instant.
        $this->freezeClock('2026-05-01T00:00:00+00:00');
    }

    public function testAddExceptionReturns201(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-15T09:00:00-04:00',
            'end_date_time' => '2026-06-15T17:00:00-04:00',
            'reason' => 'Day off',
            'is_all_day' => false,
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('exception', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    public function testAddAllDayExceptionSnapsToThePracticeLocalDay(): void
    {
        $token = $this->createTherapistAndGetToken();

        // A caller on UTC+14 marking their own day off. Read in Caracas the
        // submitted range is 1 June 10:00 to 18:00, so 1 June is the day blocked.
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-02T04:00:00+14:00',
            'end_date_time' => '2026-06-02T12:00:00+14:00',
            'reason' => 'Away',
            'is_all_day' => true,
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $exception = $this->getResponseData()['data']['exception'];

        // Caracas is UTC-4, so its midnight is 04:00 the same day in UTC.
        $this->assertSame('2026-06-01T04:00:00+00:00', $exception['start_date_time']);
        $this->assertSame('2026-06-02T04:00:00+00:00', $exception['end_date_time']);
    }

    public function testAddNonAllDayExceptionKeepsTheSubmittedRangeAndEmitsItInUtc(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-02T04:00:00+14:00',
            'end_date_time' => '2026-06-02T12:00:00+14:00',
            'reason' => 'Doctor appointment',
            'is_all_day' => false,
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $exception = $this->getResponseData()['data']['exception'];

        // The instants the caller sent, unsnapped and restated in UTC. Echoing
        // their offset would disagree with the list response. See ADR-0001.
        $this->assertSame('2026-06-01T14:00:00+00:00', $exception['start_date_time']);
        $this->assertSame('2026-06-01T22:00:00+00:00', $exception['end_date_time']);
        $this->assertSame('2026-05-01T00:00:00+00:00', $exception['created_at']);
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

    public function testAddExceptionUnauthenticatedReturns401(): void
    {
        $this->jsonRequest('POST', '/api/therapist/schedule/exceptions', [
            'start_date_time' => '2026-06-15T09:00:00-04:00',
            'end_date_time' => '2026-06-15T17:00:00-04:00',
            'reason' => 'Day off',
            'is_all_day' => false,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
