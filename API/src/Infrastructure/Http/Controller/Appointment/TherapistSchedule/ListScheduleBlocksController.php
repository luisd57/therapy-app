<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Appointment\TherapistSchedule;

use App\Application\Appointment\Handler\GetTherapistScheduleHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ResolvesCurrentUserTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListScheduleBlocksController extends AbstractController
{
    use ApiResponseTrait;
    use ResolvesCurrentUserTrait;

    #[Route('/api/therapist/schedule', name: 'api_therapist_schedule_list', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(GetTherapistScheduleHandler $handler): JsonResponse
    {
        $schedules = $handler->__invoke($this->currentUserId());

        return $this->success([
            'schedules' => $schedules->map(fn ($dto) => $dto->toArray())->toArray(),
            'count' => $schedules->count(),
        ]);
    }
}
