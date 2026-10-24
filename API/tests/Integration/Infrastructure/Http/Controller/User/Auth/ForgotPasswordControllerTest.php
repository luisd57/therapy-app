<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\LongEmail;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\WireShapes;

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
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertEqualsCanonicalizing(WireShapes::SUCCESS_ENVELOPE, array_keys($data), 'envelope keys');
        $this->assertEqualsCanonicalizing(['message'], array_keys(Json::arrayAt($data, 'data')), 'data keys');
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

    public function testForgotPasswordRejectsAnEmailOverTheLengthCap(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => LongEmail::ofLength(255),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Email must not exceed 254 characters', Json::at($this->getResponseData(), 'error', 'details', 'email'));
    }

    public function testForgotPasswordAcceptsAnEmailAtTheLengthCap(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => LongEmail::ofLength(254),
        ]);

        $this->assertResponseIsSuccessful();
    }
}
