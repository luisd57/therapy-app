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

        $result = $handler->handle(new GetAvailableSlotsInputDTO(
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
            $result = $handler->handle(new LockSlotInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
            ));

            return $this->created($result->toArray());
        } catch (SlotNotAvailableException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 409);
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
            $result = $handler->handle(new RequestAppointmentInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
                fullName: $data['full_name'],
                phone: $data['phone'],
                email: $data['email'],
                city: $data['city'],
                country: $data['country'],
                lockToken: $data['lock_token'] ?? null,
            ));

            return $this->created([
                'appointment' => $result->toArray(),
                'message' => 'Your appointment request has been submitted. You will receive a confirmation email shortly.',
            ]);
        } catch (SlotNotAvailableException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 409);
        } catch (InvalidLockTokenException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 400);
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
        }

        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone number is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['city'])) {
            $errors['city'] = 'City is required';
        }

        if (empty($data['country'])) {
            $errors['country'] = 'Country is required';
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
        try {
            new \DateTimeImmutable($dateTime);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
