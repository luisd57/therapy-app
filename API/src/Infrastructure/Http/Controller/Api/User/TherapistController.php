<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\User;

use App\Application\Shared\DTO\PaginationInputDTO;
use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\DTO\Input\ListPatientsInputDTO;
use App\Application\User\DTO\Input\ResendInvitationInputDTO;
use App\Application\User\DTO\Input\RevokeInvitationInputDTO;
use App\Application\User\Handler\GetUserHandler;
use App\Application\User\Handler\InvitePatientHandler;
use App\Application\User\Handler\ListInvitationsHandler;
use App\Application\User\Handler\ListPatientsHandler;
use App\Application\User\Handler\ResendInvitationHandler;
use App\Application\User\Handler\RevokeInvitationHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/therapist')]
#[IsGranted('ROLE_THERAPIST')]
final class TherapistController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/me', name: 'api_therapist_me', methods: ['GET'])]
    public function me(GetUserHandler $handler): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        try {
            $user = $handler->__invoke($currentUser->getId()->getValue());

            return $this->success($user->toArray());
        } catch (UserNotFoundException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }

    #[Route('/patients', name: 'api_therapist_list_patients', methods: ['GET'])]
    public function listPatients(Request $request, ListPatientsHandler $handler): JsonResponse
    {
        $pagination = new PaginationInputDTO(
            page: $request->query->getInt('page') ?: null,
            limit: $request->query->getInt('limit') ?: null,
        );

        $result = $handler->__invoke(new ListPatientsInputDTO(
            pagination: $pagination,
        ));

        return $this->success([
            'patients' => $result->items->map(fn ($dto) => $dto->toArray())->toArray(),
            'pagination' => $result->toMeta(),
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

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        try {
            $invitation = $handler->__invoke(new InvitePatientInputDTO(
                email: $data['email'],
                patientName: $data['patient_name'],
                therapistId: $currentUser->getId()->getValue(),
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

    #[Route('/invitations/{id}/resend', name: 'api_therapist_resend_invitation', methods: ['POST'])]
    public function resendInvitation(string $id, ResendInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler->__invoke(new ResendInvitationInputDTO(tokenId: $id));

            return $this->created([
                'invitation' => $invitation->toArray(),
                'message' => 'Invitation resent successfully.',
            ]);
        } catch (InvitationNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (InvalidTokenException $exception) {
            return $this->error($exception->getMessage(), 'INVALID_INVITATION_STATE', 409);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 'INVALID_INVITATION_STATE', 409);
        }
    }

    #[Route('/invitations/{id}/revoke', name: 'api_therapist_revoke_invitation', methods: ['POST'])]
    public function revokeInvitation(string $id, RevokeInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler->__invoke(new RevokeInvitationInputDTO(tokenId: $id));

            return $this->success([
                'invitation' => $invitation->toArray(),
                'message' => 'Invitation revoked successfully.',
            ]);
        } catch (InvitationNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\DomainException $exception) {
            return $this->error($exception->getMessage(), 'INVALID_INVITATION_STATE', 409);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateInviteRequest(array $data): array
    {
        $errors = [];

        $emailViolations = $this->validator->validate($data['email'] ?? '', [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = $emailViolations[0]->getMessage();
        }

        $nameViolations = $this->validator->validate($data['patient_name'] ?? '', [
            new Assert\NotBlank(message: 'Patient name is required'),
            new Assert\Length(max: 255, maxMessage: 'Patient name must not exceed 255 characters'),
        ]);

        if (count($nameViolations) > 0) {
            $errors['patient_name'] = $nameViolations[0]->getMessage();
        }

        return $errors;
    }
}
