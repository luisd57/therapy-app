<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\DTO\Input\DeleteTherapistScheduleInputDTO;
use App\Application\Appointment\DTO\Input\ListScheduleExceptionsInputDTO;
use App\Application\Appointment\DTO\Input\RemoveScheduleExceptionInputDTO;
use App\Application\Appointment\DTO\Input\SetTherapistScheduleInputDTO;
use App\Application\Appointment\DTO\Input\UpdateTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\AddScheduleExceptionHandler;
use App\Application\Appointment\Handler\DeleteTherapistScheduleHandler;
use App\Application\Appointment\Handler\GetTherapistScheduleHandler;
use App\Application\Appointment\Handler\ListScheduleExceptionsHandler;
use App\Application\Appointment\Handler\RemoveScheduleExceptionHandler;
use App\Application\Appointment\Handler\SetTherapistScheduleHandler;
use App\Application\Appointment\Handler\UpdateTherapistScheduleHandler;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/therapist/schedule')]
#[IsGranted('ROLE_THERAPIST')]
final class TherapistScheduleController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'api_therapist_schedule_list', methods: ['GET'])]
    public function listSchedules(GetTherapistScheduleHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $schedules = $handler->handle($currentUser->getId());

        return $this->success([
            'schedules' => $schedules->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $schedules->count(),
        ]);
    }

    #[Route('', name: 'api_therapist_schedule_create', methods: ['POST'])]
    public function createSchedule(Request $request, SetTherapistScheduleHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateScheduleRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $result = $handler->handle(new SetTherapistScheduleInputDTO(
                therapistId: $currentUser->getId(),
                dayOfWeek: (int) $data['day_of_week'],
                startTime: $data['start_time'],
                endTime: $data['end_time'],
                supportsOnline: $data['supports_online'] ?? true,
                supportsInPerson: $data['supports_in_person'] ?? true,
            ));

            return $this->created([
                'schedule' => $result->toArray(),
                'message' => 'Schedule block created successfully.',
            ]);
        } catch (ScheduleConflictException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 409);
        }
    }

    #[Route('/{id}', name: 'api_therapist_schedule_update', methods: ['PUT'])]
    public function updateSchedule(string $id, Request $request, UpdateTherapistScheduleHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateScheduleRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $result = $handler->handle(new UpdateTherapistScheduleInputDTO(
                scheduleId: $id,
                therapistId: $currentUser->getId(),
                dayOfWeek: (int) $data['day_of_week'],
                startTime: $data['start_time'],
                endTime: $data['end_time'],
                supportsOnline: $data['supports_online'] ?? true,
                supportsInPerson: $data['supports_in_person'] ?? true,
            ));

            return $this->success([
                'schedule' => $result->toArray(),
                'message' => 'Schedule block updated successfully.',
            ]);
        } catch (ScheduleConflictException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $this->isNotFound($e) ? 404 : 409);
        }
    }

    #[Route('/{id}', name: 'api_therapist_schedule_delete', methods: ['DELETE'])]
    public function deleteSchedule(string $id, DeleteTherapistScheduleHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $handler->handle(new DeleteTherapistScheduleInputDTO(
                scheduleId: $id,
                therapistId: $currentUser->getId(),
            ));

            return $this->noContent();
        } catch (ScheduleConflictException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    #[Route('/exceptions', name: 'api_therapist_schedule_exceptions_list', methods: ['GET'])]
    public function listExceptions(Request $request, ListScheduleExceptionsHandler $handler): JsonResponse
    {
        $from = $request->query->get('from', '');
        $to = $request->query->get('to', '');

        $errors = $this->validateDateRange($from, $to);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $exceptions = $handler->handle(new ListScheduleExceptionsInputDTO(
            therapistId: $currentUser->getId(),
            from: $from,
            to: $to,
        ));

        return $this->success([
            'exceptions' => $exceptions->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $exceptions->count(),
        ]);
    }

    #[Route('/exceptions', name: 'api_therapist_schedule_exceptions_create', methods: ['POST'])]
    public function addException(Request $request, AddScheduleExceptionHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateExceptionRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $result = $handler->handle(new AddScheduleExceptionInputDTO(
            therapistId: $currentUser->getId(),
            startDateTime: $data['start_date_time'],
            endDateTime: $data['end_date_time'],
            reason: $data['reason'] ?? '',
            isAllDay: $data['is_all_day'] ?? false,
        ));

        return $this->created([
            'exception' => $result->toArray(),
            'message' => 'Schedule exception created successfully.',
        ]);
    }

    #[Route('/exceptions/{id}', name: 'api_therapist_schedule_exceptions_delete', methods: ['DELETE'])]
    public function removeException(string $id, RemoveScheduleExceptionHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $handler->handle(new RemoveScheduleExceptionInputDTO(
                exceptionId: $id,
                therapistId: $currentUser->getId(),
            ));

            return $this->noContent();
        } catch (ScheduleConflictException $e) {
            return $this->notFound($e->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateScheduleRequest(array $data): array
    {
        $errors = [];

        if (!isset($data['day_of_week'])) {
            $errors['day_of_week'] = 'Day of week is required';
        } elseif (!is_numeric($data['day_of_week']) || (int) $data['day_of_week'] < 1 || (int) $data['day_of_week'] > 7) {
            $errors['day_of_week'] = 'Day of week must be between 1 (Monday) and 7 (Sunday)';
        }

        if (empty($data['start_time'])) {
            $errors['start_time'] = 'Start time is required';
        } elseif (!$this->isValidTimeFormat($data['start_time'])) {
            $errors['start_time'] = 'Start time must be in HH:MM format';
        }

        if (empty($data['end_time'])) {
            $errors['end_time'] = 'End time is required';
        } elseif (!$this->isValidTimeFormat($data['end_time'])) {
            $errors['end_time'] = 'End time must be in HH:MM format';
        }

        if (empty($errors['start_time']) && empty($errors['end_time']) && ($data['start_time'] ?? '') >= ($data['end_time'] ?? '')) {
            $errors['end_time'] = 'End time must be after start time';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateExceptionRequest(array $data): array
    {
        $errors = [];

        if (empty($data['start_date_time'])) {
            $errors['start_date_time'] = 'Start date/time is required';
        } elseif (!$this->isValidDateTime($data['start_date_time'])) {
            $errors['start_date_time'] = 'Start date/time must be a valid ISO-8601 datetime';
        }

        if (empty($data['end_date_time'])) {
            $errors['end_date_time'] = 'End date/time is required';
        } elseif (!$this->isValidDateTime($data['end_date_time'])) {
            $errors['end_date_time'] = 'End date/time must be a valid ISO-8601 datetime';
        }

        if (empty($errors) && ($data['start_date_time'] ?? '') >= ($data['end_date_time'] ?? '')) {
            $errors['end_date_time'] = 'End date/time must be after start date/time';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateDateRange(string $from, string $to): array
    {
        $errors = [];

        if ($from === '') {
            $errors['from'] = 'From date is required';
        } elseif (!$this->isValidDate($from)) {
            $errors['from'] = 'From date must be a valid date (YYYY-MM-DD)';
        }

        if ($to === '') {
            $errors['to'] = 'To date is required';
        } elseif (!$this->isValidDate($to)) {
            $errors['to'] = 'To date must be a valid date (YYYY-MM-DD)';
        }

        if (empty($errors) && $from > $to) {
            $errors['from'] = 'From date must be before or equal to To date';
        }

        return $errors;
    }

    private function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}$/', $time);
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function isValidDateTime(string $dateTime): bool
    {
        try {
            new \DateTimeImmutable($dateTime);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function isNotFound(ScheduleConflictException $e): bool
    {
        return str_contains($e->getMessage(), 'not found');
    }
}
