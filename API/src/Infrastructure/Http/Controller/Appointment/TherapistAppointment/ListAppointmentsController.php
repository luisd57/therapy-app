<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\Handler\ListAppointmentsHandler;
use App\Application\Shared\DTO\PaginationInputDTO;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ListAppointmentsController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/appointments', name: 'api_therapist_appointments_list', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, ListAppointmentsHandler $handler): JsonResponse
    {
        $status = $request->query->get('status');

        if ($status !== null && $status !== '') {
            $validStatuses = ['REQUESTED', 'CONFIRMED', 'COMPLETED', 'CANCELLED'];
            $violations = $this->validator->validate($status, [
                new Assert\Choice(choices: $validStatuses, message: 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)),
            ]);

            if (count($violations) > 0) {
                return $this->validationError(['status' => $violations[0]->getMessage()]);
            }
        }

        $pagination = new PaginationInputDTO(
            page: $request->query->getInt('page') ?: null,
            limit: $request->query->getInt('limit') ?: null,
        );

        $result = $handler->__invoke(new ListAppointmentsInputDTO(
            status: ($status !== null && $status !== '') ? $status : null,
            pagination: $pagination,
        ));

        return $this->success([
            'appointments' => $result->items->map(fn ($dto) => $dto->toArray())->toArray(),
            'pagination' => $result->toMeta(),
        ]);
    }
}
