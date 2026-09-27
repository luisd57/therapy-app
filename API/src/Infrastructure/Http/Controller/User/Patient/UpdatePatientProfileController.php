<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Patient;

use App\Application\User\DTO\Input\UpdatePatientProfileInputDTO;
use App\Application\User\Handler\UpdatePatientProfileHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\JsonBody;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UpdatePatientProfileController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/patient/profile', name: 'api_patient_update_profile', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_PATIENT')]
    public function __invoke(Request $request, UpdatePatientProfileHandler $handler): JsonResponse
    {
        $jsonBody = JsonBody::fromRequest($request);

        $errors = $this->validateProfileUpdateRequest($jsonBody);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $address = $jsonBody->object('address');

        try {
            $user = $handler->__invoke(new UpdatePatientProfileInputDTO(
                userId: $this->currentUserId(),
                phone: $jsonBody->optionalString('phone'),
                street: $address?->optionalString('street'),
                city: $address?->optionalString('city'),
                country: $address?->optionalString('country'),
                postalCode: $address?->optionalString('postal_code'),
                state: $address?->optionalString('state'),
            ));

            return $this->success([
                'user' => $user->toArray(),
                'message' => 'Profile updated successfully.',
            ]);
        } catch (UserNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateProfileUpdateRequest(JsonBody $jsonBody): array
    {
        $errors = [];

        // Phone validation (if provided)
        if ($jsonBody->hasNonString('phone')) {
            $errors['phone'] = 'Phone number must be a string';
        } elseif ($jsonBody->string('phone') !== '') {
            $phone = preg_replace('/[^0-9+]/', '', $jsonBody->string('phone'));
            $phoneViolations = $this->validator->validate($phone, [
                new Assert\Length(
                    min: 7,
                    max: 20,
                    minMessage: 'Phone number must be between 7 and 20 digits',
                    maxMessage: 'Phone number must be between 7 and 20 digits',
                ),
            ]);

            if (count($phoneViolations) > 0) {
                $errors['phone'] = (string) $phoneViolations->get(0)->getMessage();
            }
        }

        // Address validation (if any field is provided, all required fields must be present)
        $address = $jsonBody->object('address');
        if ($jsonBody->has('address') && $address === null) {
            $errors['address'] = 'Address must be an object';
        }

        if ($address !== null) {
            $hasAnyField = !empty($address->string('street')) || !empty($address->string('city')) || !empty($address->string('country'));

            if ($hasAnyField) {
                if (empty($address->string('street'))) {
                    $errors['address.street'] = 'Street is required when updating address';
                }
                if (empty($address->string('city'))) {
                    $errors['address.city'] = 'City is required when updating address';
                }
                if (empty($address->string('country'))) {
                    $errors['address.country'] = 'Country is required when updating address';
                }
            }

            // After the required checks, so a wrong type reports as that rather than as missing.
            $labels = ['street' => 'Street', 'city' => 'City', 'country' => 'Country', 'postal_code' => 'Postal code', 'state' => 'State'];
            foreach ($labels as $key => $label) {
                if ($address->hasNonString($key)) {
                    $errors['address.' . $key] = $label . ' must be a string';
                }
            }
        }

        return $errors;
    }
}
