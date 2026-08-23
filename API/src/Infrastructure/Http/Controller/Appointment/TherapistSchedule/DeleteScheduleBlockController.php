<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Application\Appointment\DTO\Input\DeleteTherapistScheduleInputDTO;
use App\Application\Appointment\Handler\DeleteTherapistScheduleHandler;
use App\Domain\Appointment\Exception\ScheduleConflictException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DeleteScheduleBlockController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;

    #[Route('/api/therapist/schedule/{id}', name: 'api_therapist_schedule_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(string $id, DeleteTherapistScheduleHandler $handler): JsonResponse
    {
        try {
            $handler->__invoke(new DeleteTherapistScheduleInputDTO(
                scheduleId: $id,
                therapistId: $this->currentUserId(),
            ));

            return $this->noContent();
        } catch (ScheduleConflictException $exception) {
            return $this->notFound($exception->getMessage());
        }
    }
}
