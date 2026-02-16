<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\Handler\CreateTherapistHandler;
use App\Application\User\Handler\GetUserHandler;
use App\Application\User\Handler\InvitePatientHandler;
use App\Application\User\Handler\ListInvitationsHandler;
use App\Application\User\Handler\ListPatientsHandler;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Persistence\Doctrine\User\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/therapist')]
final class TherapistController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/setup', name: 'api_therapist_setup', methods: ['POST'])]
    public function setupTherapist(Request $request, CreateTherapistHandler $handler): JsonResponse
    {
        // This endpoint should only work if no therapist exists
        // In production, you'd want additional security measures
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateSetupRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $user = $handler->handle(new CreateTherapistInputDTO(
                email: $data['email'],
                fullName: $data['full_name'],
                password: $data['password'],
            ));

            return $this->created([
                'user' => $user->toArray(),
                'message' => 'Therapist account created successfully.',
            ]);
        } catch (UserAlreadyExistsException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 409);
        }
    }

    #[Route('/me', name: 'api_therapist_me', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function me(GetUserHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $user = $handler->handle($currentUser->getId());

        return $this->success($user->toArray());
    }

    #[Route('/patients', name: 'api_therapist_list_patients', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function listPatients(ListPatientsHandler $handler): JsonResponse
    {
        $patients = $handler->handle();

        return $this->success([
            'patients' => $patients->map(fn($dto) => $dto->toArray())->toArray(),
            'count' => $patients->count(),
        ]);
    }

    #[Route('/patients/invite', name: 'api_therapist_invite_patient', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function invitePatient(Request $request, InvitePatientHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateInviteRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        try {
            $invitation = $handler->handle(new InvitePatientInputDTO(
                email: $data['email'],
                patientName: $data['patient_name'],
                therapistId: $currentUser->getId(),
            ));

            return $this->created([
                'invitation' => $invitation->toArray(),
                'message' => 'Invitation sent successfully.',
            ]);
        } catch (UserAlreadyExistsException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), 409);
        }
    }

    #[Route('/invitations', name: 'api_therapist_list_invitations', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function listInvitations(ListInvitationsHandler $handler): JsonResponse
    {
        $invitations = $handler->handle();

        return $this->success([
            'invitations' => $invitations->map(fn($dto) => $dto->toArray())->toArray(),
            'count' => $invitations->count(),
        ]);
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

    /**
     * @return array<string, string>
     */
    private function validateInviteRequest(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['patient_name'])) {
            $errors['patient_name'] = 'Patient name is required';
        }

        return $errors;
    }
}
