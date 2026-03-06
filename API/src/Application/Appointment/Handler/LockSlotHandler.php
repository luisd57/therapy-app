<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\LockSlotInputDTO;
use App\Application\Appointment\DTO\Output\SlotLockOutputDTO;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\Enum\AppointmentModality;
use App\Domain\Appointment\Id\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Service\TokenGeneratorInterface;
use Symfony\Component\Clock\ClockInterface;
use DateTimeImmutable;

final readonly class LockSlotHandler
{
    public function __construct(
        private SlotLockRepositoryInterface $slotLockRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private ClockInterface $clock,
        private int $appointmentDurationMinutes,
        private int $slotLockTtl,
    ) {
    }

    public function __invoke(LockSlotInputDTO $dto): SlotLockOutputDTO
    {
        $now = $this->clock->now();
        $startTime = new DateTimeImmutable($dto->slotStartTime);
        $modality = AppointmentModality::from($dto->modality);
        $timeSlot = TimeSlot::create($startTime, $this->appointmentDurationMinutes);

        $existingLock = $this->slotLockRepository->findActiveByTimeSlot(
            $timeSlot->getStartTime(),
            $timeSlot->getEndTime(),
        );

        if ($existingLock !== null && $existingLock->isActive($now)) {
            throw SlotNotAvailableException::alreadyLocked();
        }

        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: $timeSlot,
            modality: $modality,
            lockToken: $this->tokenGenerator->generate(32),
            ttlSeconds: $this->slotLockTtl,
            now: $now,
        );

        $this->slotLockRepository->save($lock);

        return SlotLockOutputDTO::fromEntity($lock);
    }
}
