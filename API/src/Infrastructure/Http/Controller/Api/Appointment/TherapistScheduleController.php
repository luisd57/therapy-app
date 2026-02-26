<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Appointment;

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
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use App\Infrastructure\Persistence\Doctrine\User\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/therapist/schedule')]
#[IsGranted('ROLE_THERAPIST')]
final class TherapistScheduleController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;
    use ValidatesRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'api_therapist_schedule_list', methods: ['GET'])]
    public function listSchedules(GetTherapistScheduleHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $schedules = $handler->__invoke($currentUser->getId());

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
            $result = $handler->__invoke(new SetTherapistScheduleInputDTO(
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
        } catch (ScheduleConflictException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
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
            $result = $handler->__invoke(new UpdateTherapistScheduleInputDTO(
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
        } catch (ScheduleConflictException $exception) {
            $status = str_contains($exception->getErrorCode(), 'NOT_FOUND') ? 404 : 409;

            return $this->error($exception->getMessage(), $exception->getErrorCode(), $status);
        }
    }

    #[Route('/{id}', name: 'api_therapist_schedule_delete', methods: ['DELETE'])]
    public function deleteSchedule(string $id, DeleteTherapistScheduleHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $handler->__invoke(new DeleteTherapistScheduleInputDTO(
                scheduleId: $id,
                therapistId: $currentUser->getId(),
            ));

            return $this->noContent();
        } catch (ScheduleConflictException $exception) {
            return $this->notFound($exception->getMessage());
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

        $exceptions = $handler->__invoke(new ListScheduleExceptionsInputDTO(
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

        $result = $handler->__invoke(new AddScheduleExceptionInputDTO(
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
            $handler->__invoke(new RemoveScheduleExceptionInputDTO(
                exceptionId: $id,
                therapistId: $currentUser->getId(),
            ));

            return $this->noContent();
        } catch (ScheduleConflictException $exception) {
            return $this->notFound($exception->getMessage());
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
        } else {
            $dayViolations = $this->validator->validate($data['day_of_week'], [
                new Assert\Range(min: 1, max: 7, notInRangeMessage: 'Day of week must be between 1 (Monday) and 7 (Sunday)'),
            ]);

            if (!is_numeric($data['day_of_week']) || count($dayViolations) > 0) {
                $errors['day_of_week'] = 'Day of week must be between 1 (Monday) and 7 (Sunday)';
            }
        }

        $startViolations = $this->validator->validate($data['start_time'] ?? '', [
            new Assert\NotBlank(message: 'Start time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Start time must be in HH:MM format'),
        ]);

        if (count($startViolations) > 0) {
            $errors['start_time'] = $startViolations[0]->getMessage();
        }

        $endViolations = $this->validator->validate($data['end_time'] ?? '', [
            new Assert\NotBlank(message: 'End time is required'),
            new Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'End time must be in HH:MM format'),
        ]);

        if (count($endViolations) > 0) {
            $errors['end_time'] = $endViolations[0]->getMessage();
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

        $startViolations = $this->validator->validate($data['start_date_time'] ?? '', [
            new Assert\NotBlank(message: 'Start date/time is required'),
        ]);

        if (count($startViolations) > 0) {
            $errors['start_date_time'] = $startViolations[0]->getMessage();
        } elseif (!$this->isValidDateTime($data['start_date_time'])) {
            $errors['start_date_time'] = 'Start date/time must be a valid ISO-8601 datetime';
        }

        $endViolations = $this->validator->validate($data['end_date_time'] ?? '', [
            new Assert\NotBlank(message: 'End date/time is required'),
        ]);

        if (count($endViolations) > 0) {
            $errors['end_date_time'] = $endViolations[0]->getMessage();
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

        $fromViolations = $this->validator->validate($from, [
            new Assert\NotBlank(message: 'From date is required'),
        ]);

        if (count($fromViolations) > 0) {
            $errors['from'] = $fromViolations[0]->getMessage();
        } elseif (!$this->isValidDate($from)) {
            $errors['from'] = 'From date must be a valid date (YYYY-MM-DD)';
        }

        $toViolations = $this->validator->validate($to, [
            new Assert\NotBlank(message: 'To date is required'),
        ]);

        if (count($toViolations) > 0) {
            $errors['to'] = $toViolations[0]->getMessage();
        } elseif (!$this->isValidDate($to)) {
            $errors['to'] = 'To date must be a valid date (YYYY-MM-DD)';
        }

        if (empty($errors) && $from > $to) {
            $errors['from'] = 'From date must be before or equal to To date';
        }

        return $errors;
    }
}
