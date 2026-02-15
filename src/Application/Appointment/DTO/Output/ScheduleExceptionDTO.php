<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO\Output;

use App\Domain\Appointment\Entity\ScheduleException;
use DateTimeInterface;

final readonly class ScheduleExceptionDTO
{
    public function __construct(
        public string $id,
        public string $startDateTime,
        public string $endDateTime,
        public string $reason,
        public bool $isAllDay,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(ScheduleException $exception): self
    {
        return new self(
            id: $exception->getId()->getValue(),
            startDateTime: $exception->getStartDateTime()->format(DateTimeInterface::ATOM),
            endDateTime: $exception->getEndDateTime()->format(DateTimeInterface::ATOM),
            reason: $exception->getReason(),
            isAllDay: $exception->isAllDay(),
            createdAt: $exception->getCreatedAt()->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'start_date_time' => $this->startDateTime,
            'end_date_time' => $this->endDateTime,
            'reason' => $this->reason,
            'is_all_day' => $this->isAllDay,
            'created_at' => $this->createdAt,
        ];
    }
}
