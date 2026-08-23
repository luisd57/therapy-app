<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\Handler\GetAvailableSlotsHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GetAvailableSlotsController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/appointments/available-slots', name: 'api_available_slots', methods: ['GET'])]
    public function __invoke(Request $request, GetAvailableSlotsHandler $handler): JsonResponse
    {
        $from = $request->query->get('from', '');
        $to = $request->query->get('to', '');

        $errors = $this->validateAvailableSlotsRequest($from, $to);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $modality = $request->query->get('modality');

        $result = $handler->__invoke(new GetAvailableSlotsInputDTO(
            from: $from,
            to: $to,
            modality: $modality,
        ));

        return $this->success($result->toArray());
    }

    /**
     * @return array<string, string>
     */
    private function validateAvailableSlotsRequest(string $from, string $to): array
    {
        $errors = [];

        $fromViolations = $this->validator->validate($from, [
            new Assert\NotBlank(message: 'From is required'),
        ]);

        if (count($fromViolations) > 0) {
            $errors['from'] = $fromViolations[0]->getMessage();
        } elseif (!$this->isValidInstant($from)) {
            $errors['from'] = 'From must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-01T00:00:00-04:00';
        }

        $toViolations = $this->validator->validate($to, [
            new Assert\NotBlank(message: 'To is required'),
        ]);

        if (count($toViolations) > 0) {
            $errors['to'] = $toViolations[0]->getMessage();
        } elseif (!$this->isValidInstant($to)) {
            $errors['to'] = 'To must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-08T00:00:00-04:00';
        }

        // Instants, not strings: two windows with different offsets do not sort
        // lexically in timeline order.
        if (empty($errors) && new \DateTimeImmutable($from) >= new \DateTimeImmutable($to)) {
            $errors['from'] = 'From must be before To';
        }

        return $errors;
    }
}
