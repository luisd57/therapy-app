<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\CompleteAppointmentInputDTO;
use App\Application\Appointment\Handler\CompleteAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CompleteAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/appointments/{id}/complete', name: 'api_therapist_appointments_complete', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, CompleteAppointmentHandler $handler): JsonResponse
    {
        try {
            $appointment = $handler->__invoke(new CompleteAppointmentInputDTO(
                appointmentId: $id,
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
                'message' => 'Appointment completed successfully.',
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (InvalidStatusTransitionException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }
}
