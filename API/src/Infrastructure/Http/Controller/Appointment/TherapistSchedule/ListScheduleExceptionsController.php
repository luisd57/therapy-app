<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Application\Appointment\DTO\Input\ListScheduleExceptionsInputDTO;
use App\Application\Appointment\Handler\ListScheduleExceptionsHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ListScheduleExceptionsController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/schedule/exceptions', name: 'api_therapist_schedule_exceptions_list', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, ListScheduleExceptionsHandler $handler): JsonResponse
    {
        $from = $request->query->get('from', '');
        $to = $request->query->get('to', '');

        $errors = $this->validateDateRange($from, $to);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $exceptions = $handler->__invoke(new ListScheduleExceptionsInputDTO(
            therapistId: $this->currentUserId(),
            from: $from,
            to: $to,
        ));

        return $this->success([
            'exceptions' => $exceptions->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $exceptions->count(),
        ]);
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
