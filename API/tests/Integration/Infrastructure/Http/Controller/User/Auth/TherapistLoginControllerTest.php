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
        $data = $this->getResponseData();
        $this->assertEqualsCanonicalizing(['success', 'data'], array_keys($data), 'envelope keys');
        // The token travels in the httpOnly cookie only, so no key here may carry it.
        $this->assertEqualsCanonicalizing(['user'], array_keys($data['data']), 'data keys');
        $this->assertEqualsCanonicalizing([
            'id', 'email', 'full_name', 'role', 'is_active', 'phone',
            'address', 'created_at', 'activated_at', 'timezone',
        ], array_keys($data['data']['user']), 'data.user keys');
    }

    public function testTherapistLoginMissingEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'password' => self::THERAPIST_PASSWORD,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => [
                    'email' => 'Email is required',
                ],
            ],
        ], $this->getResponseData());
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
