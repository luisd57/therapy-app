<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\Handler\BookAppointmentHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BookAppointmentController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/appointments', name: 'api_therapist_appointments_book', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, BookAppointmentHandler $handler): JsonResponse
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

    /**
     * @return array<string, string>
     */
    private function validateBookRequest(array $data): array
    {
        $errors = [];

        $slotViolations = $this->validator->validate($data['slot_start_time'] ?? '', [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = $slotViolations[0]->getMessage();
        } elseif (!$this->isValidInstant($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-01T09:30:00-04:00';
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
            new Assert\NotBlank(message: 'Phone is required'),
            new Assert\Length(max: 50, maxMessage: 'Phone must not exceed 50 characters'),
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
