<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Application\Appointment\DTO\Input\RequestAppointmentInputDTO;
use App\Application\Appointment\Handler\RequestAppointmentHandler;
use App\Domain\Appointment\Exception\InvalidLockTokenException;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestAppointmentController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/appointments/request', name: 'api_request_appointment', methods: ['POST'])]
    public function __invoke(Request $request, RequestAppointmentHandler $handler): JsonResponse
    {
        $body = JsonBody::fromRequest($request);

        $errors = $this->validateRequestAppointmentRequest($body);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new RequestAppointmentInputDTO(
                slotStartTime: $body->string('slot_start_time'),
                modality: $body->string('modality'),
                fullName: $body->string('full_name'),
                phone: $body->string('phone'),
                email: $body->string('email'),
                city: $body->string('city'),
                country: $body->string('country'),
                lockToken: $body->optionalString('lock_token'),
                requesterTimezone: $body->optionalString('timezone'),
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
    private function validateRequestAppointmentRequest(JsonBody $body): array
    {
        $errors = [];

        $slotViolations = $this->validator->validate($body->string('slot_start_time'), [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = (string) $slotViolations->get(0)->getMessage();
        } elseif (!$this->isValidInstant($body->string('slot_start_time'))) {
            $errors['slot_start_time'] = 'Slot start time must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-01T09:30:00-04:00';
        }

        $modalityViolations = $this->validator->validate($body->string('modality'), [
            new Assert\NotBlank(message: 'Modality is required'),
            new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
        ]);

        if (count($modalityViolations) > 0) {
            $errors['modality'] = (string) $modalityViolations->get(0)->getMessage();
        }

        // Optional, since older clients omit it. A fixed offset is still rejected
        // outright, because it carries no daylight-saving rules.
        if ($body->has('timezone') && !$this->isValidTimezone($body->string('timezone'))) {
            $errors['timezone'] = 'Timezone must be an IANA identifier, e.g. Europe/Madrid';
        }

        $nameViolations = $this->validator->validate($body->string('full_name'), [
            new Assert\NotBlank(message: 'Full name is required'),
            new Assert\Length(max: 255, maxMessage: 'Full name must not exceed 255 characters'),
        ]);

        if (count($nameViolations) > 0) {
            $errors['full_name'] = (string) $nameViolations->get(0)->getMessage();
        }

        $phoneViolations = $this->validator->validate($body->string('phone'), [
            new Assert\NotBlank(message: 'Phone number is required'),
            new Assert\Length(max: 50, maxMessage: 'Phone number must not exceed 50 characters'),
        ]);

        if (count($phoneViolations) > 0) {
            $errors['phone'] = (string) $phoneViolations->get(0)->getMessage();
        }

        $emailViolations = $this->validator->validate($body->string('email'), [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = (string) $emailViolations->get(0)->getMessage();
        }

        $cityViolations = $this->validator->validate($body->string('city'), [
            new Assert\NotBlank(message: 'City is required'),
            new Assert\Length(max: 255, maxMessage: 'City must not exceed 255 characters'),
        ]);

        if (count($cityViolations) > 0) {
            $errors['city'] = (string) $cityViolations->get(0)->getMessage();
        }

        $countryViolations = $this->validator->validate($body->string('country'), [
            new Assert\NotBlank(message: 'Country is required'),
            new Assert\Length(max: 255, maxMessage: 'Country must not exceed 255 characters'),
        ]);

        if (count($countryViolations) > 0) {
            $errors['country'] = (string) $countryViolations->get(0)->getMessage();
        }

        if ($body->hasNonString('lock_token')) {
            $errors['lock_token'] = 'Lock token must be a string';
        }

        return $errors;
    }
}
