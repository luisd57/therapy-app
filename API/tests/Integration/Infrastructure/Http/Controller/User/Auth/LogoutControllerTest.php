<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Infrastructure\Security\JwtCookieManager;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\KeepsBlocklistAcrossRequests;
use App\Tests\Helper\SeedsAuthFixtures;

final class LogoutControllerTest extends ApiTestCase
{
    use KeepsBlocklistAcrossRequests;
    use SeedsAuthFixtures;

    public function testLogoutClearsCookie(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/auth/logout', [], $token);

        $this->assertResponseIsSuccessful();

        // Verify the session cookie is expired after logout
        $cookie = $this->client->getCookieJar()->get(JwtCookieManager::COOKIE_NAME, '/api');
        $this->assertTrue(
            $cookie === null || $cookie->isExpired(),
            JwtCookieManager::COOKIE_NAME . ' cookie should be expired after logout',
        );
    }

    public function testLogoutWorksWithCookieToken(): void
    {
        $this->seedTherapist();

        // Login to get the cookie
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => self::THERAPIST_EMAIL, 'password' => self::THERAPIST_PASSWORD]));

        // Logout using only the cookie (no Authorization header)
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame('Successfully logged out.', $data['data']['message']);
    }

    /**
     * Clearing the cookie is not logging out: an API client keeps its copy of the
     * token, so the jti has to stop authenticating.
     */
    public function testLogoutStopsTheTokenFromAuthenticating(): void
    {
        $this->useBlocklistThatSurvivesRequests();

        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('GET', '/api/auth/me', [], $token);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/auth/logout', [], $token);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/auth/me', [], $token);
        $this->assertResponseStatusCodeSame(401);
    }
}
