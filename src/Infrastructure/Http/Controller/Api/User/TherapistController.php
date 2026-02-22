<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
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
#[IsGranted('ROLE_THERAPIST')]
final class TherapistController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/me', name: 'api_therapist_me', methods: ['GET'])]
    public function me(GetUserHandler $handler): JsonResponse
    {
        /** @var UserEntity $currentUser */
        $currentUser = $this->getUser();

        $user = $handler->__invoke($currentUser->getId());

        return $this->success($user->toArray());
    }

    #[Route('/patients', name: 'api_therapist_list_patients', methods: ['GET'])]
    public function listPatients(ListPatientsHandler $handler): JsonResponse
    {
        $patients = $handler->__invoke();

        return $this->success([
            'patients' => $patients->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $patients->count(),
        ]);
    }

    #[Route('/patients/invite', name: 'api_therapist_invite_patient', methods: ['POST'])]
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
            $invitation = $handler->__invoke(new InvitePatientInputDTO(
                email: $data['email'],
                patientName: $data['patient_name'],
                therapistId: $currentUser->getId(),
            ));

            return $this->created([
                'invitation' => $invitation->toArray(),
                'message' => 'Invitation sent successfully.',
            ]);
        } catch (UserAlreadyExistsException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }

    #[Route('/invitations', name: 'api_therapist_list_invitations', methods: ['GET'])]
    public function listInvitations(ListInvitationsHandler $handler): JsonResponse
    {
        $invitations = $handler->__invoke();

        return $this->success([
            'invitations' => $invitations->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $invitations->count(),
        ]);
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
