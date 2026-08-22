<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\DTO\Input\RevokeInvitationInputDTO;
use App\Application\User\Handler\RevokeInvitationHandler;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RevokeInvitationController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/invitations/{id}/revoke', name: 'api_therapist_revoke_invitation', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, RevokeInvitationHandler $handler): JsonResponse
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
}
