<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\GetAppointmentInputDTO;
use App\Application\Appointment\Handler\GetAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/appointments/{id}', name: 'api_therapist_appointments_show', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, GetAppointmentHandler $handler): JsonResponse
    {
        try {
            $appointment = $handler->__invoke(new GetAppointmentInputDTO(
                appointmentId: $id,
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }
}
