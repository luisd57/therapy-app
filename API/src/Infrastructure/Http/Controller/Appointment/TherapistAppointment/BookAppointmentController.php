<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistAppointment;

use App\Application\Appointment\DTO\Input\BookAppointmentInputDTO;
use App\Application\Appointment\Handler\BookAppointmentHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
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
        $jsonBody = JsonBody::fromRequest($request);

        $errors = $this->validateBookRequest($jsonBody);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $appointment = $handler->__invoke(new BookAppointmentInputDTO(
                slotStartTime: $jsonBody->string('slot_start_time'),
                modality: $jsonBody->string('modality'),
                fullName: $jsonBody->string('full_name'),
                phone: $jsonBody->string('phone'),
                email: $jsonBody->string('email'),
                city: $jsonBody->string('city'),
                country: $jsonBody->string('country'),
                patientId: $jsonBody->optionalString('patient_id'),
            ));

            return $this->created([
                'appointment' => $appointment->toArray(),
                'message' => 'Appointment booked successfully.',
            ]);
        } catch (UserNotFoundException $exception) {
            // patient_id comes from the request body here, unlike the actions that read it
            // off the authenticated principal, so this one really can fire.
            return $this->notFound($exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateBookRequest(JsonBody $jsonBody): array
    {
        $errors = [];

        $slotViolations = $this->validator->validate($jsonBody->string('slot_start_time'), [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = (string) $slotViolations->get(0)->getMessage();
        } elseif (!$this->isValidInstant($jsonBody->string('slot_start_time'))) {
            $errors['slot_start_time'] = 'Slot start time must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-01T09:30:00-04:00';
        }

        $modalityViolations = $this->validator->validate($jsonBody->string('modality'), [
            new Assert\NotBlank(message: 'Modality is required'),
            new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
        ]);

        if (count($modalityViolations) > 0) {
            $errors['modality'] = (string) $modalityViolations->get(0)->getMessage();
        }

        $nameViolations = $this->validator->validate($jsonBody->string('full_name'), [
            new Assert\NotBlank(message: 'Full name is required'),
            new Assert\Length(max: 255, maxMessage: 'Full name must not exceed 255 characters'),
        ]);

        if (count($nameViolations) > 0) {
            $errors['full_name'] = (string) $nameViolations->get(0)->getMessage();
        }

        $phoneViolations = $this->validator->validate($jsonBody->string('phone'), [
            new Assert\NotBlank(message: 'Phone is required'),
            new Assert\Length(max: 50, maxMessage: 'Phone must not exceed 50 characters'),
        ]);

        if (count($phoneViolations) > 0) {
            $errors['phone'] = (string) $phoneViolations->get(0)->getMessage();
        }

        $emailViolations = $this->validator->validate($jsonBody->string('email'), [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = (string) $emailViolations->get(0)->getMessage();
        }

        $cityViolations = $this->validator->validate($jsonBody->string('city'), [
            new Assert\NotBlank(message: 'City is required'),
            new Assert\Length(max: 255, maxMessage: 'City must not exceed 255 characters'),
        ]);

        if (count($cityViolations) > 0) {
            $errors['city'] = (string) $cityViolations->get(0)->getMessage();
        }

        $countryViolations = $this->validator->validate($jsonBody->string('country'), [
            new Assert\NotBlank(message: 'Country is required'),
            new Assert\Length(max: 255, maxMessage: 'Country must not exceed 255 characters'),
        ]);

        if (count($countryViolations) > 0) {
            $errors['country'] = (string) $countryViolations->get(0)->getMessage();
        }

        if ($jsonBody->hasNonString('patient_id')) {
            $errors['patient_id'] = 'Patient ID must be a string';
        }

        return $errors;
    }
}
