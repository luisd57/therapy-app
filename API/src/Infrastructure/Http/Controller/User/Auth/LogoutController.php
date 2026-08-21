<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User\Auth;

use App\Domain\User\Service\JwtBlocklistInterface;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Security\JwtCookieManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly JwtCookieManager $jwtCookieManager,
    ) {}

    #[Route('/api/auth/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(
        Request $request,
        JwtBlocklistInterface $jwtBlocklist,
        JWTEncoderInterface $jwtEncoder,
    ): JsonResponse {
        $token = $request->cookies->get(JwtCookieManager::COOKIE_NAME, '');
        if ($token === '') {
            $authHeader = $request->headers->get('Authorization', '');
            $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
        }

        if ($token === '') {
            return $this->error('No token provided', 'NO_TOKEN', 400);
        }

        try {
            $payload = $jwtEncoder->decode($token);
            $jti = $payload['jti'] ?? null;

            if ($jti === null) {
                return $this->error('Token has no JTI claim', 'INVALID_TOKEN', 400);
            }

            $exp = $payload['exp'] ?? 0;
            $ttlSeconds = max(0, $exp - time());
            $jwtBlocklist->revoke($jti, $ttlSeconds);

            $response = $this->success(['message' => 'Successfully logged out.']);
            $response->headers->setCookie($this->jwtCookieManager->createExpiredCookie());

            return $response;
        } catch (\Exception) {
            return $this->error('Invalid token', 'INVALID_TOKEN', 400);
        }
    }
}
