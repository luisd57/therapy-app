<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Application\Appointment\DTO\Input\UpdateTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\UpdateTherapistScheduleHandler;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use App\Infrastructure\Http\Controller\ValidatesScheduleBlockRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdateScheduleBlockController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;
    use ValidatesScheduleBlockRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/schedule/{id}', name: 'api_therapist_schedule_update', methods: ['PUT'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, Request $request, UpdateTherapistScheduleHandler $handler): JsonResponse
    {
        $body = JsonBody::fromRequest($request);

        $errors = $this->validateScheduleBlockRequest($this->validator, $body);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new UpdateTherapistScheduleInputDTO(
                scheduleId: $id,
                therapistId: $this->currentUserId(),
                dayOfWeek: $body->int('day_of_week') ?? 0,
                startTime: $body->string('start_time'),
                endTime: $body->string('end_time'),
                supportsOnline: $body->bool('supports_online') ?? true,
                supportsInPerson: $body->bool('supports_in_person') ?? true,
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
}
