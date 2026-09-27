<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\UpdatePaymentStatusInputDTO;
use App\Application\Appointment\Handler\UpdatePaymentStatusHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UpdatePaymentStatusController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/appointments/{id}/payment', name: 'api_therapist_appointments_payment', methods: ['PATCH'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, Request $request, UpdatePaymentStatusHandler $handler): JsonResponse
    {
        $paymentVerified = JsonBody::fromRequest($request)->bool('payment_verified');

        if ($paymentVerified === null) {
            return $this->validationError(['payment_verified' => 'payment_verified is required and must be a boolean']);
        }

        try {
            $appointment = $handler->__invoke(new UpdatePaymentStatusInputDTO(
                appointmentId: $id,
                paymentVerified: $paymentVerified,
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
                'message' => 'Payment status updated successfully.',
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }
}
