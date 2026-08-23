<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\PublicAppointment;

use App\Application\Appointment\DTO\Input\LockSlotInputDTO;
use App\Application\Appointment\Handler\LockSlotHandler;
use App\Domain\Appointment\Exception\SlotNotAvailableException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidationHelperTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LockSlotController extends AbstractController
{
    use ApiResponseTrait;
    use ValidationHelperTrait;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/appointments/lock-slot', name: 'api_lock_slot', methods: ['POST'])]
    public function __invoke(Request $request, LockSlotHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateLockSlotRequest($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $result = $handler->__invoke(new LockSlotInputDTO(
                slotStartTime: $data['slot_start_time'],
                modality: $data['modality'],
            ));

            return $this->created($result->toArray());
        } catch (SlotNotAvailableException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        }
    }

    /**
     * @return array<string, string>
     */
    private function validateLockSlotRequest(array $data): array
    {
        $errors = [];

        $slotViolations = $this->validator->validate($data['slot_start_time'] ?? '', [
            new Assert\NotBlank(message: 'Slot start time is required'),
        ]);

        if (count($slotViolations) > 0) {
            $errors['slot_start_time'] = $slotViolations[0]->getMessage();
        } elseif (!$this->isValidInstant($data['slot_start_time'])) {
            $errors['slot_start_time'] = 'Slot start time must be an ISO-8601 instant with a UTC offset, e.g. 2026-06-01T09:30:00-04:00';
        }

        $modalityViolations = $this->validator->validate($data['modality'] ?? '', [
            new Assert\NotBlank(message: 'Modality is required'),
            new Assert\Choice(choices: ['ONLINE', 'IN_PERSON'], message: 'Modality must be ONLINE or IN_PERSON'),
        ]);

        if (count($modalityViolations) > 0) {
            $errors['modality'] = $modalityViolations[0]->getMessage();
        }

        return $errors;
    }
}
