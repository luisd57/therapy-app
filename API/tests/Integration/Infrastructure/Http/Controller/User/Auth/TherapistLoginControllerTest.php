<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class TherapistLoginControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testTherapistLoginSuccess(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
    }

    public function testTherapistLoginMissingEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'password' => self::THERAPIST_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testTherapistLoginWrongPasswordReturns401(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => self::THERAPIST_EMAIL,
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testTherapistLoginNonExistentUserReturns401(): void
    {
        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => 'nonexistent@test.com',
            'password' => self::THERAPIST_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
