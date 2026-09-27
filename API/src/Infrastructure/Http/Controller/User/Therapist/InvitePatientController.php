<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\Handler\InvitePatientHandler;
use App\Domain\User\Exception\UserAlreadyExistsException;
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

final class InvitePatientController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/therapist/patients/invite', name: 'api_therapist_invite_patient', methods: ['POST'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, InvitePatientHandler $handler): JsonResponse
    {
        $jsonBody = JsonBody::fromRequest($request);

        $errors = $this->validateInviteRequest($jsonBody);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $invitation = $handler->__invoke(new InvitePatientInputDTO(
                email: $jsonBody->string('email'),
                patientName: $jsonBody->string('patient_name'),
                therapistId: $this->currentUserId(),
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
    private function validateInviteRequest(JsonBody $jsonBody): array
    {
        $errors = [];

        $emailViolations = $this->validator->validate($jsonBody->string('email'), [
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Invalid email format'),
        ]);

        if (count($emailViolations) > 0) {
            $errors['email'] = (string) $emailViolations->get(0)->getMessage();
        }

        $nameViolations = $this->validator->validate($jsonBody->string('patient_name'), [
            new Assert\NotBlank(message: 'Patient name is required'),
            new Assert\Length(max: 255, maxMessage: 'Patient name must not exceed 255 characters'),
        ]);

        if (count($nameViolations) > 0) {
            $errors['patient_name'] = (string) $nameViolations->get(0)->getMessage();
        }

        return $errors;
    }
}
