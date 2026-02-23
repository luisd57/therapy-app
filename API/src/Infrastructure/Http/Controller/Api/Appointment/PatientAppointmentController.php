<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Appointment;

use App\Application\Appointment\DTO\Input\PatientRequestAppointmentInputDTO;
use App\Application\Appointment\Handler\PatientRequestAppointmentHandler;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Domain\User\Exception\IncompleteProfileException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Persistence\Doctrine\User\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/patient/appointments')]
#[IsGranted('ROLE_PATIENT')]
final class PatientAppointmentController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('', name: 'api_patient_request_appointment', methods: ['POST'])]
    public function requestAppointment(
        Request $request,
        PatientRequestAppointmentHandler $handler,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $result = $handler->__invoke(new PatientRequestAppointmentInputDTO(
                patientId: $currentUser->getId(),
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
                lockToken: $data['lock_token'] ?? null,
            ));

            $patientData = array_intersect_key($result->toArray(), array_flip([
                'id', 'start_time', 'end_time', 'modality', 'status',
                'patient_id', 'created_at',
            ]));

            return $this->created([
                'appointment' => $patientData,
                'message' => 'Your appointment request has been submitted. You will receive a confirmation email shortly.',
            ]);
        } catch (SlotNotAvailableException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (InvalidLockTokenException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 400);
        } catch (IncompleteProfileException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 422);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateRequest(array $data): array
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

    private function isValidDateTime(string $dateTime): bool
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $dateTime) !== false
            || \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateTime) !== false;
    }
}
