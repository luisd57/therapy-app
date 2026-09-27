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
        $body = JsonBody::fromRequest($request);

        $errors = $this->validateProfileUpdateRequest($body);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $address = $body->object('address');

        try {
            $user = $handler->__invoke(new UpdatePatientProfileInputDTO(
                userId: $this->currentUserId(),
                phone: $body->optionalString('phone'),
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
    private function validateProfileUpdateRequest(JsonBody $body): array
    {
        $errors = [];

        // Phone validation (if provided)
        if ($body->hasNonString('phone')) {
            $errors['phone'] = 'Phone number must be a string';
        } elseif ($body->string('phone') !== '') {
            $phone = preg_replace('/[^0-9+]/', '', $body->string('phone'));
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
        $address = $body->object('address');
        if ($address !== null) {
            $hasAnyField = $address->string('street') !== '' || $address->string('city') !== '' || $address->string('country') !== '';

            if ($hasAnyField) {
                if ($address->string('street') === '') {
                    $errors['address.street'] = 'Street is required when updating address';
                }
                if ($address->string('city') === '') {
                    $errors['address.city'] = 'City is required when updating address';
                }
                if ($address->string('country') === '') {
                    $errors['address.country'] = 'Country is required when updating address';
                }
            }
        }

        return $errors;
    }
}
