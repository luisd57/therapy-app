<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Therapist;

use App\Application\Shared\DTO\PaginationInputDTO;
use App\Application\User\DTO\Input\ListPatientsInputDTO;
use App\Application\User\Handler\ListPatientsHandler;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListPatientsController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/therapist/patients', name: 'api_therapist_list_patients', methods: ['GET'])]
    #[IsGranted('ROLE_THERAPIST')]
    public function __invoke(Request $request, ListPatientsHandler $handler): JsonResponse
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
}
