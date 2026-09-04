<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http;

use App\Infrastructure\Security\JwtCookieManager;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

/**
 * How the JWT travels, rather than what any one auth endpoint returns.
 *
 * These span several requests across login, /me and the protected endpoints, so they belong to no
 * single controller.
 */
final class JwtCookieTransportTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testLoginSetsHttpOnlyCookie(): void
    {
        $this->seedTherapist();

        $this->loginAsTherapist();

        $this->assertResponseIsSuccessful();

        $cookie = $this->client->getCookieJar()->get(JwtCookieManager::COOKIE_NAME, '/api');
        $this->assertNotNull($cookie, 'Login should set ' . JwtCookieManager::COOKIE_NAME . ' cookie');
        $this->assertNotEmpty($cookie->getValue());

        // Verify response body does not contain the token
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('token', $data['data']);
        $this->assertArrayHasKey('user', $data['data']);
    }

    /**
     * JwtCookieManagerTest pins what the manager builds. This pins that the login response is what
     * the manager built, so a hand-rolled Set-Cookie somewhere in the auth path is caught.
     */
    public function testTheLoginCookieIsEmittedWithItsSecurityAttributes(): void
    {
        $this->seedTherapist();

        $this->loginAsTherapist();

        $setCookie = $this->client->getResponse()->headers->get('Set-Cookie');
        $this->assertNotNull($setCookie);
        $this->assertStringStartsWith(JwtCookieManager::COOKIE_NAME . '=', $setCookie);
        $this->assertStringContainsString('path=/api', $setCookie);
        $this->assertStringContainsString('httponly', $setCookie);
        $this->assertStringContainsString('samesite=lax', $setCookie);
    }

    public function testCookieAuthenticatesOnProtectedEndpoint(): void
    {
        $this->seedTherapist();

        $this->loginAsTherapist();
        $this->assertResponseIsSuccessful();

        // Access protected endpoint - cookie is sent automatically by the cookie jar
        $this->client->request('GET', '/api/therapist/me');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(self::THERAPIST_EMAIL, $data['data']['email']);
    }

    public function testBearerTokenStillWorksOnProtectedEndpoints(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Clear the cookie jar so only Bearer is used
        $this->client->getCookieJar()->expire(JwtCookieManager::COOKIE_NAME, '/api');

        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(self::THERAPIST_EMAIL, $data['data']['email']);
    }

    public function testFullLoginThenAccessProtectedResourceFlow(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Use the JWT token to access a protected endpoint
        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(self::THERAPIST_EMAIL, $data['data']['email']);
    }

    public function testSecondLoginReplacesSessionCookieSingleSession(): void
    {
        // Single-session-per-browser: logging in again (any role) replaces the
        // one session cookie, so the prior session no longer applies.
        $this->seedTherapist();
        $this->seedActivatedPatient();

        $this->client->request('POST', '/api/auth/patient/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => self::PATIENT_EMAIL, 'password' => self::PATIENT_PASSWORD]));
        $patientCookie = $this->client->getCookieJar()->get(JwtCookieManager::COOKIE_NAME, '/api');
        $this->assertNotNull($patientCookie);
        $patientToken = $patientCookie->getValue();

        $this->loginAsTherapist();
        $therapistCookie = $this->client->getCookieJar()->get(JwtCookieManager::COOKIE_NAME, '/api');
        $this->assertNotNull($therapistCookie);
        $this->assertNotSame($patientToken, $therapistCookie->getValue(), 'Second login should replace the session cookie');

        // The active session is now the therapist's - cookie sent automatically.
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame('ROLE_THERAPIST', $data['data']['role']);
        $this->assertSame(self::THERAPIST_EMAIL, $data['data']['email']);
    }

    /**
     * Raw client request rather than jsonRequest(), which expires the cookie these tests observe.
     */
    private function loginAsTherapist(): void
    {
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => self::THERAPIST_EMAIL, 'password' => self::THERAPIST_PASSWORD]));
    }
}
