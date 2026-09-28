<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsTherapistSchedule;

final class GetNextAvailableWeekControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    public function testNextAvailableWeekReturns200WithSchedule(): void
    {
        // Saturday 05:00 in Caracas. The week runs from that practice-local midnight and
        // takes in Monday's block, so the first week searched is the one returned.
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(
            ['found', 'week_start', 'week_end', 'modality', 'practice_timezone', 'slots', 'total_slots'],
            array_keys(Json::arrayAt($data, 'data')),
            'data keys',
        );
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'slots')), 'data.slots is a list');
        $this->assertEqualsCanonicalizing(
            ['start_time', 'end_time', 'duration_minutes'],
            array_keys(Json::arrayAt($data, 'data', 'slots', 0)),
            'data.slots[0] keys',
        );
        $this->assertTrue(Json::at($data, 'data', 'found'));
        $this->assertSame('2026-05-30T04:00:00+00:00', Json::at($data, 'data', 'week_start'));
        $this->assertSame('2026-06-06T04:00:00+00:00', Json::at($data, 'data', 'week_end'));
        $this->assertGreaterThan(0, Json::at($data, 'data', 'total_slots'));
        $this->assertSame('America/Caracas', Json::at($data, 'data', 'practice_timezone'));
    }

    public function testNextAvailableWeekReturnsFoundFalseWithNoSchedule(): void
    {
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertFalse(Json::at($data, 'data', 'found'));
        $this->assertNull(Json::at($data, 'data', 'week_start'));
        $this->assertSame(0, Json::at($data, 'data', 'total_slots'));
    }

    public function testNextAvailableWeekWithModalityFilter(): void
    {
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week?modality=ONLINE');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ONLINE', Json::at($data, 'data', 'modality'));
    }

    public function testNextAvailableWeekReturns422WithInvalidModality(): void
    {
        $this->client->request('GET', '/api/appointments/next-available-week?modality=INVALID');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }
}
