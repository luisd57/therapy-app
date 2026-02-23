<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class HealthController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/health', name: 'api_health', methods: ['GET'])]
    public function health(EntityManagerInterface $entityManager): JsonResponse
    {
        $databaseOk = false;

        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
            $databaseOk = true;
        } catch (\Exception) {
            // Database unreachable
        }

        return new JsonResponse([
            'status' => $databaseOk ? 'healthy' : 'unhealthy',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $databaseOk ? 200 : 503);
    }

    #[Route('/', name: 'api_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->success([
            'name' => 'Therapy App API',
            'version' => '1.0.0',
            'endpoints' => [
                'health' => '/api/health',
                'auth' => '/api/auth/*',
                'therapist' => '/api/therapist/*',
                'patient' => '/api/patient/*',
            ],
        ]);
    }
}
