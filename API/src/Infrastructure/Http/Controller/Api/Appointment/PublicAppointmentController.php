<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Appointment;

use App\Application\Appointment\DTO\Input\GetAvailableSlotsInputDTO;
use App\Application\Appointment\DTO\Input\LockSlotInputDTO;
use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\Handler\GetAvailableSlotsHandler;
use App\Application\Appointment\Handler\LockSlotHandler;
use App\Application\Appointment\Handler\RequestAppointmentHandler;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/appointments')]
final class PublicAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/available-slots', name: 'api_available_slots', methods: ['GET'])]
    public function availableSlots(Request $request, GetAvailableSlotsHandler $handler): JsonResponse
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

    #[Route('/lock-slot', name: 'api_lock_slot', methods: ['POST'])]
    public function lockSlot(Request $request, LockSlotHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLockSlotRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new LockSlotInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
            ));

            return $this->created($result->toArray());
        } catch (SlotNotAvailableException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }

    #[Route('/request', name: 'api_request_appointment', methods: ['POST'])]
    public function requestAppointment(Request $request, RequestAppointmentHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateRequestAppointmentRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new RequestAppointmentInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
                fullName: $data['full_name'],
                phone: $data['phone'],
                email: $data['email'],
                city: $data['city'],
                country: $data['country'],
                lockToken: $data['lock_token'] ?? null,
            ));

            $publicData = array_intersect_key($result->toArray(), array_flip([
                'id', 'start_time', 'end_time', 'modality', 'status', 'created_at',
            ]));

            return $this->created([
                'appointment' => $publicData,
                'message' => 'Your appointment request has been submitted. You will receive a confirmation email shortly.',
            ]);
        } catch (SlotNotAvailableException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (InvalidLockTokenException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 400);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateAvailableSlotsRequest(string $from, string $to): array
    {
        $errors = [];

        if ($from === '') {
            $errors['from'] = 'From date is required';
        } elseif (!$this->isValidDate($from)) {
            $errors['from'] = 'From date must be a valid date (YYYY-MM-DD)';
        }

        if ($to === '') {
            $errors['to'] = 'To date is required';
        } elseif (!$this->isValidDate($to)) {
            $errors['to'] = 'To date must be a valid date (YYYY-MM-DD)';
        }

        if (empty($errors) && $from > $to) {
            $errors['from'] = 'From date must be before or equal to To date';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateLockSlotRequest(array $data): array
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

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRequestAppointmentRequest(array $data): array
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
            $errors['phone'] = 'Phone number is required';
        } elseif (mb_strlen($data['phone']) > 50) {
            $errors['phone'] = 'Phone number must not exceed 50 characters';
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

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function isValidDateTime(string $dateTime): bool
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateTime) !== false
            || \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateTime) !== false;
    }
}
