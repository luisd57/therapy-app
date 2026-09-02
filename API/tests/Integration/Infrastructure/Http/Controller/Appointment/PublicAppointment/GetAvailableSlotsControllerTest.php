<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsTherapistSchedule;

final class GetAvailableSlotsControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures below are dated June 2026. Pin now so they cannot rot into the past.
        $this->freezeClock('2026-05-30 09:00:00');
    }

    public function testAvailableSlotsReturns200WithValidParams(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday. The window is the client's own instants.
        $this->client->request(
            'GET',
            '/api/appointments/available-slots'
            . '?from=' . urlencode('2026-06-01T00:00:00-04:00')
            . '&to=' . urlencode('2026-06-02T00:00:00-04:00'),
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('2026-06-01T04:00:00+00:00', $data['data']['from']);
        $this->assertSame('America/Caracas', $data['data']['practice_timezone']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);

        // Flat list, and every instant is emitted in UTC regardless of the
        // offset the caller used.
        $this->assertArrayHasKey('slots', $data['data']);
        $this->assertSame('2026-06-01T12:00:00+00:00', $data['data']['slots'][0]['start_time']);
    }

    public function testAvailableSlotsReturns422WithMissingFrom(): void
    {
        $this->client->request('GET', '/api/appointments/available-slots');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testAvailableSlotsReturns422WithInvalidDateFormat(): void
    {
        $this->client->request('GET', '/api/appointments/available-slots?from=not-a-date&to=also-bad');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testAvailableSlotsWithModalityFilter(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01 is a Monday
        $this->client->request(
            'GET',
            '/api/appointments/available-slots'
            . '?from=' . urlencode('2026-06-01T00:00:00-04:00')
            . '&to=' . urlencode('2026-06-02T00:00:00-04:00')
            . '&modality=ONLINE',
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ONLINE', $data['data']['modality']);
        $this->assertGreaterThan(0, $data['data']['total_slots']);
    }
}
