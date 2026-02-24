<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiLoginLimiter,
        private readonly RateLimiterFactory $apiPublicLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        $route = $request->attributes->get('_route', '');
        $clientIp = $request->getClientIp() ?? 'unknown';

        $limiter = $this->resolveLimiter($route, $clientIp);
        if ($limiter === null) {
            return;
        }

        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $requestEvent->setResponse(new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                ],
            ], 429, [
                'Retry-After' => $retryAfter->getTimestamp() - time(),
                'X-RateLimit-Limit' => $limit->getLimit(),
                'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            ]));
        }
    }

    private function resolveLimiter(string $route, string $clientIp): ?\Symfony\Component\RateLimiter\LimiterInterface
    {
        return match ($route) {
            'api_therapist_login', 'api_patient_login' => $this->apiLoginLimiter->create($clientIp),
            'api_forgot_password', 'api_lock_slot', 'api_request_appointment',
            'api_validate_invitation', 'api_register', 'api_reset_password' => $this->apiPublicLimiter->create($clientIp),
            default => null,
        };
    }
}
