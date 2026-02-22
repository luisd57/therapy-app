<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\Handler\CreateTherapistHandler;
use App\Domain\User\Exception\TherapistAlreadyExistsException;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/therapist')]
final class TherapistSetupController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/setup', name: 'api_therapist_setup', methods: ['POST'])]
    public function setupTherapist(Request $request, CreateTherapistHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateSetupRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $handler->__invoke(new CreateTherapistInputDTO(
                email: $data['email'],
                fullName: $data['full_name'],
                password: $data['password'],
            ));

            return $this->created([
                'user' => $user->toArray(),
                'message' => 'Therapist account created successfully.',
            ]);
        } catch (TherapistAlreadyExistsException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (UserAlreadyExistsException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateSetupRequest(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        return $errors;
    }
}
