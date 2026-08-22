<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\DTO\Input\ResendInvitationInputDTO;
use App\Application\User\Handler\ResendInvitationHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ResendInvitationController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/invitations/{id}/resend', name: 'api_therapist_resend_invitation', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, ResendInvitationHandler $handler): JsonResponse
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
}
