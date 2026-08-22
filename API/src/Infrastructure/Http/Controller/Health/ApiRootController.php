<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Health;

use App\Infrastructure\Http\Controller\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiRootController extends AbstractController
{
    use ApiResponseTrait;

    #[Route('/api/', name: 'api_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
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
