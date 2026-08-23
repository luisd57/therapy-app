<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\CancelAppointmentInputDTO;
use App\Application\Appointment\Handler\CancelAppointmentHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CancelAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/appointments/{id}/cancel', name: 'api_therapist_appointments_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, CancelAppointmentHandler $handler): JsonResponse
    {
        try {
            $appointment = $handler->__invoke(new CancelAppointmentInputDTO(
                appointmentId: $id,
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
                'message' => 'Appointment cancelled successfully.',
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (InvalidStatusTransitionException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }
}
