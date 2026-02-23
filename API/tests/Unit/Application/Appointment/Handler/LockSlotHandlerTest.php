<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\LockSlotInputDTO;
use App\Application\Appointment\Handler\LockSlotHandler;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Service\TokenGeneratorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LockSlotHandlerTest extends TestCase
{
    private SlotLockRepositoryInterface&MockObject $slotLockRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private LockSlotHandler $handler;

    protected function setUp(): void
    {
        $this->slotLockRepository = $this->createMock(SlotLockRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);

        $this->handler = new LockSlotHandler(
            $this->slotLockRepository,
            $this->tokenGenerator,
            50,
            600,
        );
    }

    public function testHandleSuccessCreatesAndSavesSlotLock(): void
    {
        $this->slotLockRepository
            ->method('findActiveByTimeSlot')
            ->willReturn(null);

        $this->tokenGenerator
            ->method('generate')
            ->willReturn('generated-lock-token');

        $this->slotLockRepository
            ->expects($this->once())
            ->method('save');

        $input = new LockSlotInputDTO(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('generated-lock-token', $result->lockToken);
        $this->assertNotEmpty($result->slotStartTime);
        $this->assertNotEmpty($result->slotEndTime);
        $this->assertNotEmpty($result->expiresAt);
    }

    public function testHandleAlreadyLockedThrowsSlotNotAvailableException(): void
    {
        $timeSlot = TimeSlot::create(new \DateTimeImmutable('2025-06-02 09:00:00'), 50);

        $existingLock = SlotLock::reconstitute(
            id: SlotLockId::generate(),
            timeSlot: $timeSlot,
            modality: AppointmentModality::ONLINE,
            lockToken: 'existing-token',
            createdAt: new \DateTimeImmutable(),
            expiresAt: new \DateTimeImmutable('+10 minutes'),
        );

        $this->slotLockRepository
            ->method('findActiveByTimeSlot')
            ->willReturn($existingLock);

        $this->slotLockRepository
            ->expects($this->never())
            ->method('save');

        $input = new LockSlotInputDTO(
            slotStartTime: '2025-06-02 09:00:00',
            modality: 'ONLINE',
        );

        $this->expectException(SlotNotAvailableException::class);
        $this->handler->__invoke($input);
    }
}
