<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\SeedsTherapistSchedule;

final class GetNextAvailableWeekControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    public function testNextAvailableWeekReturns200WithSchedule(): void
    {
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['found']);
        $this->assertNotNull($data['data']['week_start']);
        $this->assertNotNull($data['data']['week_end']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);
        $this->assertArrayHasKey('slots', $data['data']);
        $this->assertSame('America/Caracas', $data['data']['practice_timezone']);
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
        $this->assertFalse($data['data']['found']);
        $this->assertNull($data['data']['week_start']);
        $this->assertSame(0, $data['data']['total_slots']);
    }

    public function testNextAvailableWeekWithModalityFilter(): void
    {
        $this->createTherapistWithSchedule();

        $this->client->request('GET', '/api/appointments/next-available-week?modality=ONLINE');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ONLINE', $data['data']['modality']);
    }

    public function testNextAvailableWeekReturns422WithInvalidModality(): void
    {
        $this->client->request('GET', '/api/appointments/next-available-week?modality=INVALID');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }
}
