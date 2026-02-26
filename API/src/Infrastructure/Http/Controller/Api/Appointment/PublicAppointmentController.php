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
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/appointments')]
final class PublicAppointmentController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;
    use ValidatesRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

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

        $fromViolations = $this->validator->validate($from, [
            new Assert\NotBlank(message: 'From date is required'),
        ]);

        if (count($fromViolations) > 0) {
            $errors['from'] = $fromViolations[0]->getMessage();
        } elseif (!$this->isValidDate($from)) {
            $errors['from'] = 'From date must be a valid date (YYYY-MM-DD)';
        }

        $toViolations = $this->validator->validate($to, [
            new Assert\NotBlank(message: 'To date is required'),
        ]);

        if (count($toViolations) > 0) {
            $errors['to'] = $toViolations[0]->getMessage();
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

        $slotViolations = $this->validator->validate($data['slot_start_time'] ?? '', [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = $slotViolations[0]->getMessage();
        } elseif (!$this->isValidDateTime($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time must be a valid ISO-8601 datetime';
        }

        $modalityViolations = $this->validator->validate($data['modality'] ?? '', [
            new Assert\NotBlank(message: 'Modality is required'),
            new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
        ]);

        if (count($modalityViolations) > 0) {
            $errors['modality'] = $modalityViolations[0]->getMessage();
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRequestAppointmentRequest(array $data): array
    {
        $errors = [];

        $slotViolations = $this->validator->validate($data['slot_start_time'] ?? '', [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = $slotViolations[0]->getMessage();
        } elseif (!$this->isValidDateTime($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time must be a valid ISO-8601 datetime';
        }

        $modalityViolations = $this->validator->validate($data['modality'] ?? '', [
            new Assert\NotBlank(message: 'Modality is required'),
            new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
        ]);

        if (count($modalityViolations) > 0) {
            $errors['modality'] = $modalityViolations[0]->getMessage();
        }

        $nameViolations = $this->validator->validate($data['full_name'] ?? '', [
            new Assert\NotBlank(message: 'Full name is required'),
            new Assert\Length(max: 255, maxMessage: 'Full name must not exceed 255 characters'),
        ]);

        if (count($nameViolations) > 0) {
            $errors['full_name'] = $nameViolations[0]->getMessage();
        }

        $phoneViolations = $this->validator->validate($data['phone'] ?? '', [
            new Assert\NotBlank(message: 'Phone number is required'),
            new Assert\Length(max: 50, maxMessage: 'Phone number must not exceed 50 characters'),
        ]);

        if (count($phoneViolations) > 0) {
            $errors['phone'] = $phoneViolations[0]->getMessage();
        }

        $emailViolations = $this->validator->validate($data['email'] ?? '', [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = $emailViolations[0]->getMessage();
        }

        $cityViolations = $this->validator->validate($data['city'] ?? '', [
            new Assert\NotBlank(message: 'City is required'),
            new Assert\Length(max: 255, maxMessage: 'City must not exceed 255 characters'),
        ]);

        if (count($cityViolations) > 0) {
            $errors['city'] = $cityViolations[0]->getMessage();
        }

        $countryViolations = $this->validator->validate($data['country'] ?? '', [
            new Assert\NotBlank(message: 'Country is required'),
            new Assert\Length(max: 255, maxMessage: 'Country must not exceed 255 characters'),
        ]);

        if (count($countryViolations) > 0) {
            $errors['country'] = $countryViolations[0]->getMessage();
        }

        return $errors;
    }
}
