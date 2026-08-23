<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\SeedsTherapistSchedule;

final class RequestAppointmentControllerTest extends ApiTestCase
{
    use SeedsTherapistSchedule;

    public function testRequestAppointmentReturns201(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        // 09:30 Caracas (13:30 UTC) is an offered start: 08:00 plus a multiple of the
        // 30-minute increment, and a full session still fits before 18:00.
        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'New York',
            'country' => 'US',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('appointment', $data['data']);
        $this->assertArrayHasKey('message', $data['data']);
    }

    public function testRequestAppointmentReturns422WithMissingFields(): void
    {
        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testRequestAppointmentReturns409WhenSlotNotAvailable(): void
    {
        // Create therapist but no schedule, so no slots are available
        $userRepo = self::getContainer()->get(UserRepositoryInterface::class);
        $therapist = DomainTestHelper::createTherapist();
        $userRepo->save($therapist);

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T10:00:00-04:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'New York',
            'country' => 'US',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testRequestAppointmentRecordsTheRequestersTimezone(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            // The same instant the browse response gave, expressed in the
            // requester's own offset - Madrid in summer is UTC+2.
            'slot_start_time' => '2026-06-01T15:30:00+02:00',
            'modality' => 'ONLINE',
            'full_name' => 'Ana Torres',
            'phone' => '+34600000000',
            'email' => 'ana@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $appointment = $this->getResponseData()['data']['appointment'];
        $this->assertSame('2026-06-01T13:30:00+00:00', $appointment['start_time']);
    }

    public function testRequestAppointmentRejectsAFixedOffsetAsTimezone(): void
    {
        $this->freezeClock('2026-05-30 09:00:00');
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00-04:00',
            'modality' => 'ONLINE',
            'full_name' => 'Ana Torres',
            'phone' => '+34600000000',
            'email' => 'ana@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
            // Carries no daylight-saving rules, so it would be wrong half the year.
            'timezone' => '+02:00',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('timezone', $this->getResponseData()['error']['details']);
    }

    public function testRequestAppointmentRejectsADatetimeWithoutAnOffset(): void
    {
        $this->createTherapistWithSchedule();

        $this->jsonRequest('POST', '/api/appointments/request', [
            'slot_start_time' => '2026-06-01T09:30:00',
            'modality' => 'ONLINE',
            'full_name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@test.com',
            'city' => 'Madrid',
            'country' => 'ES',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('slot_start_time', $data['error']['details']);
    }
}
