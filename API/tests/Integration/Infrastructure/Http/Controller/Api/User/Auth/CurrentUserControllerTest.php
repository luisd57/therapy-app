<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class CurrentUserControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testAuthMeWithValidCookieReturns200(): void
    {
        $this->seedTherapist();

        // Login to set the cookie
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => self::THERAPIST_EMAIL, 'password' => self::THERAPIST_PASSWORD]));

        // Call /auth/me - cookie sent automatically
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(self::THERAPIST_EMAIL, $data['data']['email']);
        $this->assertSame('ROLE_THERAPIST', $data['data']['role']);
    }

    public function testAuthMeWithoutCookieReturns401(): void
    {
        $this->jsonRequest('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }
}
