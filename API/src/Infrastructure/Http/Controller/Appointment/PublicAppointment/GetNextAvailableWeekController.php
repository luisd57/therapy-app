<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Application\Appointment\DTO\Input\GetNextAvailableWeekInputDTO;
use App\Application\Appointment\Handler\GetNextAvailableWeekHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class GetNextAvailableWeekController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/appointments/next-available-week', name: 'api_next_available_week', methods: ['GET'])]
    public function __invoke(Request $request, GetNextAvailableWeekHandler $handler): JsonResponse
    {
        $modality = $request->query->get('modality');

        if ($modality !== null) {
            $modalityViolations = $this->validator->validate($modality, [
                new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
            ]);

            if (count($modalityViolations) > 0) {
                return $this->validationError(['modality' => $modalityViolations[0]->getMessage()]);
            }
        }

        $result = $handler->__invoke(new GetNextAvailableWeekInputDTO(
            modality: $modality,
        ));

        return $this->success($result->toArray());
    }
}
