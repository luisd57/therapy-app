<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\Handler\InvitePatientHandler;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InvitePatientController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/patients/invite', name: 'api_therapist_invite_patient', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, InvitePatientHandler $handler): JsonResponse
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
