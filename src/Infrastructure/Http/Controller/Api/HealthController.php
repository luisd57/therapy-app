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
        $checks = [
            'api' => true,
            'database' => false,
        ];

        try {
            $entityManager->getConnection()->executeQuery('SELECT 1');
            $checks['database'] = true;
        } catch (\Exception) {
            $checks['database'] = false;
        }

        $status = $checks['database'] ? 200 : 503;

        return new JsonResponse([
            'status' => $checks['database'] ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $status);
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
