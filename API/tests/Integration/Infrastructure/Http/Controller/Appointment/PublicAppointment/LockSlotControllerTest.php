<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsTherapistSchedule;

final class LockSlotControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    public function testLockSlotReturns201WhenSlotAvailable(): void
    {
        $this->createTherapistWithSchedule();

        // 2026-06-01T09:00:00 is a Monday at 09:00, within the 08:00-18:00 schedule
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('lock_token', $data['data']);
        $this->assertArrayHasKey('expires_at', $data['data']);
    }

    public function testLockSlotResponseEmitsUtcInstantsWhateverOffsetTheCallerSent(): void
    {
        $this->freezeClock('2026-05-30T09:00:00+00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData()['data'];

        // The handler parses the caller's string, so the lock carries their zone
        // until it is formatted. See ADR-0001.
        $this->assertSame('2026-06-01T13:00:00+00:00', $data['slot_start_time']);
        // End and expiry follow from configured duration and TTL, so pin the zone
        // rather than a value that moves when either is retuned.
        $this->assertStringEndsWith('+00:00', $data['slot_end_time']);
        $this->assertStringEndsWith('+00:00', $data['expires_at']);
    }

    public function testLockSlotReturns422WithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/appointments/lock-slot', []);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testLockSlotReturns409WhenSlotAlreadyLocked(): void
    {
        $this->createTherapistWithSchedule();

        // Lock the slot once
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Try to lock the same slot again
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testLockSlotRejectsADatetimeWithoutAnOffset(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:00:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('slot_start_time', $data['error']['details']);
    }

    public function testLockSlotAcceptsZuluAndOffsetFormsAsTheSameInstant(): void
    {
        $this->createTherapistWithSchedule();

        // 13:30 UTC and 09:30-04:00 are the same moment, so the second must
        // collide with the first rather than being treated as a different slot.
        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T13:30:00Z',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
        ]);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testLockSlotRejectsACalendarDateThatDoesNotExist(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/lock-slot', [
            'slot_start_time' => '2026-02-31T09:00:00-04:00',
            'modality' => 'ONLINE',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
