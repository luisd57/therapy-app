<?php

declare(strict_types=1);

namespace App\Application\Appointment\Handler;

use App\Application\Appointment\DTO\Input\LockSlotInputDTO;
use App\Application\Appointment\DTO\Output\SlotLockDTO;
use App\Domain\Appointment\Entity\SlotLock;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\Appointment\Repository\SlotLockRepositoryInterface;
use App\Domain\Appointment\ValueObject\AppointmentModality;
use App\Domain\Appointment\ValueObject\SlotLockId;
use App\Domain\Appointment\ValueObject\TimeSlot;
use App\Domain\User\Service\TokenGeneratorInterface;
use DateTimeImmutable;

final readonly class LockSlotHandler
{
    public function __construct(
        private SlotLockRepositoryInterface $slotLockRepository,
        private TokenGeneratorInterface $tokenGenerator,
        private int $appointmentDurationMinutes,
        private int $slotLockTtl,
    ) {
    }

    public function handle(LockSlotInputDTO $input): SlotLockDTO
    {
        $startTime = new DateTimeImmutable($input->slotStartTime);
        $modality = AppointmentModality::from($input->modality);
        $timeSlot = TimeSlot::create($startTime, $this->appointmentDurationMinutes);

        $existingLock = $this->slotLockRepository->findActiveByTimeSlot(
            $timeSlot->getStartTime(),
            $timeSlot->getEndTime(),
        );

        if ($existingLock !== null && $existingLock->isActive()) {
            throw SlotNotAvailableException::alreadyLocked();
        }

        $lock = SlotLock::create(
            id: SlotLockId::generate(),
            timeSlot: $timeSlot,
            modality: $modality,
            lockToken: $this->tokenGenerator->generate(32),
            ttlSeconds: $this->slotLockTtl,
        );

        $this->slotLockRepository->save($lock);

        return SlotLockDTO::fromEntity($lock);
    }
}
