<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Domain\Appointment\Repository\AppointmentRepositoryInterface;
use App\Domain\Appointment\Repository\ScheduleExceptionRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\Json;
use App\Tests\Helper\SeedsTherapistSchedule;
use App\Tests\Helper\WireShapes;

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
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(
            WireShapes::NEXT_AVAILABLE_WEEK,
            array_keys(Json::arrayAt($data, 'data')),
            'data keys',
        );
        $this->assertTrue(array_is_list(Json::arrayAt($data, 'data', 'slots')), 'data.slots is a list');
        $this->assertEqualsCanonicalizing(
            WireShapes::SLOT,
            array_keys(Json::arrayAt($data, 'data', 'slots', 0)),
            'data.slots[0] keys',
        );
        $this->assertTrue(Json::at($data, 'data', 'found'));
        $this->assertSame('2026-05-30T04:00:00+00:00', Json::at($data, 'data', 'week_start'));
        $this->assertSame('2026-06-06T04:00:00+00:00', Json::at($data, 'data', 'week_end'));
        $this->assertGreaterThan(0, Json::at($data, 'data', 'total_slots'));
        $this->assertSame('America/Caracas', Json::at($data, 'data', 'practice_timezone'));
    }

    public function testSkipsAWeekClosedByAScheduleException(): void
    {
        $this->freezeClock('2026-05-30 09:00:00+00:00');
        $this->createTherapistWithSchedule();
        // Covers the whole Monday block of the first week, 08:00-18:00 in Caracas.
        $this->seedScheduleException('2026-06-01 12:00:00+00:00', '2026-06-01 22:00:00+00:00');

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue(Json::at($data, 'data', 'found'));
        $this->assertSame('2026-06-06T04:00:00+00:00', Json::at($data, 'data', 'week_start'));
        $this->assertSame('2026-06-13T04:00:00+00:00', Json::at($data, 'data', 'week_end'));
    }

    public function testLeavesOutASlotUnderAConfirmedAppointment(): void
    {
        $this->freezeClock('2026-05-30 09:00:00+00:00');
        $this->createTherapistWithSchedule();
        // 16:00 in Caracas. Offered before the booking, so its absence after is the Appointment's doing.
        $this->assertContains('2026-06-01T20:00:00+00:00', $this->requestFirstWeekSlotStarts());

        $this->seedConfirmedAppointment('2026-06-01 20:00:00+00:00');

        $startTimes = $this->requestFirstWeekSlotStarts();
        $this->assertNotContains('2026-06-01T20:00:00+00:00', $startTimes);
        $this->assertContains('2026-06-01T12:00:00+00:00', $startTimes);
    }

    /** @return list<string> start times of the Slots returned, after checking the first week came back */
    private function requestFirstWeekSlotStarts(): array
    {
        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue(Json::at($data, 'data', 'found'));
        $this->assertSame('2026-05-30T04:00:00+00:00', Json::at($data, 'data', 'week_start'));

        return array_values(array_map(
            fn (mixed $slot) => Json::stringAt($slot, 'start_time'),
            Json::arrayAt($data, 'data', 'slots'),
        ));
    }

    private function seedConfirmedAppointment(string $start): void
    {
        self::getContainer()->get(AppointmentRepositoryInterface::class)->save(
            DomainTestHelper::createConfirmedAppointment(startTime: new \DateTimeImmutable($start)),
        );
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

    private function seedScheduleException(string $start, string $end): void
    {
        $therapist = self::getContainer()->get(UserRepositoryInterface::class)->findSingleTherapist();
        self::getContainer()->get(ScheduleExceptionRepositoryInterface::class)->save(
            DomainTestHelper::createScheduleException(
                therapist: $therapist,
                startDateTime: new \DateTimeImmutable($start),
                endDateTime: new \DateTimeImmutable($end),
            ),
        );
    }
}
