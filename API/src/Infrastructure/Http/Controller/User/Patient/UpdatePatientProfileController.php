<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Patient;

use App\Application\User\DTO\Input\UpdatePatientProfileInputDTO;
use App\Application\User\Handler\UpdatePatientProfileHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
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
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateProfileUpdateRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $handler->__invoke(new UpdatePatientProfileInputDTO(
                userId: $this->currentUserId(),
                phone: $data['phone'] ?? null,
                street: $data['address']['street'] ?? null,
                city: $data['address']['city'] ?? null,
                country: $data['address']['country'] ?? null,
                postalCode: $data['address']['postal_code'] ?? null,
                state: $data['address']['state'] ?? null,
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
    private function validateProfileUpdateRequest(array $data): array
    {
        $errors = [];

        // Phone validation (if provided)
        if (isset($data['phone']) && !empty($data['phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $data['phone']);
            $phoneViolations = $this->validator->validate($phone, [
                new Assert\Length(
                    min: 7,
                    max: 20,
                    minMessage: 'Phone number must be between 7 and 20 digits',
                    maxMessage: 'Phone number must be between 7 and 20 digits',
                ),
            ]);

            if (count($phoneViolations) > 0) {
                $errors['phone'] = $phoneViolations[0]->getMessage();
            }
        }

        // Address validation (if any field is provided, all required fields must be present)
        if (isset($data['address']) && is_array($data['address'])) {
            $address = $data['address'];
            $hasAnyField = !empty($address['street']) || !empty($address['city']) || !empty($address['country']);

            if ($hasAnyField) {
                if (empty($address['street'])) {
                    $errors['address.street'] = 'Street is required when updating address';
                }
                if (empty($address['city'])) {
                    $errors['address.city'] = 'City is required when updating address';
                }
                if (empty($address['country'])) {
                    $errors['address.country'] = 'Country is required when updating address';
                }
            }
        }

        return $errors;
    }
}
