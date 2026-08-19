<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class ForgotPasswordControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testForgotPasswordExistingEmailReturns200(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => self::THERAPIST_EMAIL,
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testForgotPasswordNonExistentEmailReturns200(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => 'nonexistent@test.com',
        ]);

        // Always returns 200 to prevent email enumeration
        $this->assertResponseIsSuccessful();
    }

    public function testForgotPasswordInvalidEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
