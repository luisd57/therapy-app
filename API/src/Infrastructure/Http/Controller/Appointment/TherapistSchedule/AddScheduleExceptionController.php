<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Application\Appointment\DTO\Input\AddScheduleExceptionInputDTO;
use App\Application\Appointment\Handler\AddScheduleExceptionHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AddScheduleExceptionController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/schedule/exceptions', name: 'api_therapist_schedule_exceptions_create', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, AddScheduleExceptionHandler $handler): JsonResponse
    {
        $jsonBody = JsonBody::fromRequest($request);

        $errors = $this->validateExceptionRequest($jsonBody);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $result = $handler->__invoke(new AddScheduleExceptionInputDTO(
            therapistId: $this->currentUserId(),
            startDateTime: $jsonBody->string('start_date_time'),
            endDateTime: $jsonBody->string('end_date_time'),
            reason: $jsonBody->string('reason'),
            isAllDay: $jsonBody->bool('is_all_day') ?? false,
        ));

        return $this->created([
            'exception' => $result->toArray(),
            'message' => 'Schedule exception created successfully.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validateExceptionRequest(JsonBody $jsonBody): array
    {
        $errors = [];

        $startViolations = $this->validator->validate($jsonBody->string('start_date_time'), [
            new Assert\NotBlank(message: 'Start date/time is required'),
        ]);

        if (count($startViolations) > 0) {
            $errors['start_date_time'] = (string) $startViolations->get(0)->getMessage();
        } elseif (!$this->isValidInstant($jsonBody->string('start_date_time'))) {
            $errors['start_date_time'] = 'Start date/time must be an ISO-8601 instant with a UTC offset';
        }

        $endViolations = $this->validator->validate($jsonBody->string('end_date_time'), [
            new Assert\NotBlank(message: 'End date/time is required'),
        ]);

        if (count($endViolations) > 0) {
            $errors['end_date_time'] = (string) $endViolations->get(0)->getMessage();
        } elseif (!$this->isValidInstant($jsonBody->string('end_date_time'))) {
            $errors['end_date_time'] = 'End date/time must be an ISO-8601 instant with a UTC offset';
        }

        // Compare instants, not strings. Lexically '09:00:00-04:00' sorts before
        // '10:00:00+14:00' while being four hours later on the timeline, so a
        // string comparison would wave an inverted range through.
        if (empty($errors)
            && new \DateTimeImmutable($jsonBody->string('start_date_time')) >= new \DateTimeImmutable($jsonBody->string('end_date_time'))
        ) {
            $errors['end_date_time'] = 'End date/time must be after start date/time';
        }

        if ($jsonBody->hasNonString('reason')) {
            $errors['reason'] = 'Reason must be a string';
        }

        if ($jsonBody->hasNonBool('is_all_day')) {
            $errors['is_all_day'] = 'All day must be true or false';
        }

        return $errors;
    }
}
