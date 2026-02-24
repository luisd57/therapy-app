<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Appointment;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\DTO\Input\CancelAppointmentInputDTO;
use App\Application\Appointment\DTO\Input\CompleteAppointmentInputDTO;
use App\Application\Appointment\DTO\Input\ConfirmAppointmentInputDTO;
use App\Application\Appointment\DTO\Input\GetAppointmentInputDTO;
use App\Application\Appointment\DTO\Input\ListAppointmentsInputDTO;
use App\Application\Appointment\DTO\Input\UpdatePaymentStatusInputDTO;
use App\Application\Shared\DTO\PaginationInputDTO;
use App\Application\Appointment\Handler\BookAppointmentHandler;
use App\Application\Appointment\Handler\CancelAppointmentHandler;
use App\Application\Appointment\Handler\CompleteAppointmentHandler;
use App\Application\Appointment\Handler\ConfirmAppointmentHandler;
use App\Application\Appointment\Handler\GetAppointmentHandler;
use App\Application\Appointment\Handler\ListAppointmentsHandler;
use App\Application\Appointment\Handler\UpdatePaymentStatusHandler;
use App\Domain\Appointment\Exception\AppointmentNotFoundException;
use App\Domain\Appointment\Exception\InvalidStatusTransitionException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/therapist/appointments')]
#[IsGranted('ROLE_THERAPIST')]
final class TherapistAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'api_therapist_appointments_list', methods: ['GET'])]
    public function list(Request $request, ListAppointmentsHandler $handler): JsonResponse
    {
        $status = $request->query->get('status');

        if ($status !== null && $status !== '') {
            $validStatuses = ['REQUESTED', 'CONFIRMED', 'COMPLETED', 'CANCELLED'];
            if (!in_array($status, $validStatuses, true)) {
                return $this->validationError(['status' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)]);
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

    #[Route('/{id}', name: 'api_therapist_appointments_show', methods: ['GET'])]
    public function show(string $id, GetAppointmentHandler $handler): JsonResponse
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

    #[Route('/{id}/confirm', name: 'api_therapist_appointments_confirm', methods: ['POST'])]
    public function confirm(string $id, ConfirmAppointmentHandler $handler): JsonResponse
    {
        try {
            $appointment = $handler->__invoke(new ConfirmAppointmentInputDTO(
                appointmentId: $id,
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
                'message' => 'Appointment confirmed successfully.',
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (InvalidStatusTransitionException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }

    #[Route('/{id}/complete', name: 'api_therapist_appointments_complete', methods: ['POST'])]
    public function complete(string $id, CompleteAppointmentHandler $handler): JsonResponse
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

    #[Route('/{id}/cancel', name: 'api_therapist_appointments_cancel', methods: ['POST'])]
    public function cancel(string $id, CancelAppointmentHandler $handler): JsonResponse
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

    #[Route('', name: 'api_therapist_appointments_book', methods: ['POST'])]
    public function book(Request $request, BookAppointmentHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateBookRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $appointment = $handler->__invoke(new BookAppointmentInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
                fullName: $data['full_name'],
                phone: $data['phone'],
                email: $data['email'],
                city: $data['city'],
                country: $data['country'],
                patientId: $data['patient_id'] ?? null,
            ));

            return $this->created([
                'appointment' => $appointment->toArray(),
                'message' => 'Appointment booked successfully.',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/payment', name: 'api_therapist_appointments_payment', methods: ['PATCH'])]
    public function updatePayment(string $id, Request $request, UpdatePaymentStatusHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['payment_verified']) || !is_bool($data['payment_verified'])) {
            return $this->validationError(['payment_verified' => 'payment_verified is required and must be a boolean']);
        }

        try {
            $appointment = $handler->__invoke(new UpdatePaymentStatusInputDTO(
                appointmentId: $id,
                paymentVerified: $data['payment_verified'],
            ));

            return $this->success([
                'appointment' => $appointment->toArray(),
                'message' => 'Payment status updated successfully.',
            ]);
        } catch (AppointmentNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateBookRequest(array $data): array
    {
        $errors = [];

        if (empty($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time is required';
        } elseif (!$this->isValidDateTime($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time must be a valid ISO-8601 datetime';
        }

        if (empty($data['modality'])) {
            $errors['modality'] = 'Modality is required';
        } elseif (!in_array($data['modality'], ['ONLINE', 'IN_PERSON'], true)) {
            $errors['modality'] = 'Modality must be ONLINE or IN_PERSON';
        }

        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        } elseif (mb_strlen($data['full_name']) > 255) {
            $errors['full_name'] = 'Full name must not exceed 255 characters';
        }

        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone is required';
        } elseif (mb_strlen($data['phone']) > 50) {
            $errors['phone'] = 'Phone must not exceed 50 characters';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['city'])) {
            $errors['city'] = 'City is required';
        } elseif (mb_strlen($data['city']) > 255) {
            $errors['city'] = 'City must not exceed 255 characters';
        }

        if (empty($data['country'])) {
            $errors['country'] = 'Country is required';
        } elseif (mb_strlen($data['country']) > 255) {
            $errors['country'] = 'Country must not exceed 255 characters';
        }

        return $errors;
    }

    private function isValidDateTime(string $dateTime): bool
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateTime) !== false
            || \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateTime) !== false;
    }
}
