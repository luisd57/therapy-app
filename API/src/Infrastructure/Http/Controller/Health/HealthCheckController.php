<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Health;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthCheckController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $entityManager): JsonResponse
    {
        $databaseOk = false;

        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
            $databaseOk = true;
        } catch (\Exception) {
            // Database unreachable
        }

        // Intentionally bypasses ApiResponseTrait envelope for compatibility with
        // health check probes and load balancers that expect a simple status response.
        return new JsonResponse([
            'status' => $databaseOk ? 'healthy' : 'unhealthy',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $databaseOk ? 200 : 503);
    }
}
