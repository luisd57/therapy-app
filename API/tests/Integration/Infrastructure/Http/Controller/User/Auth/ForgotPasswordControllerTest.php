<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\Json;
use App\Tests\Helper\RefusedEmails;
use App\Tests\Helper\SeedsAuthFixtures;
use App\Tests\Helper\WireShapes;
use PHPUnit\Framework\Attributes\DataProviderExternal;

/** No input fails both email rules, so the 422 tests pin the body shape, not which message wins. */
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

    public function testForgotPasswordMissingEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', []);

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

    public function testForgotPasswordInvalidEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => [
                    'email' => 'Invalid email format',
                ],
            ],
        ], $this->getResponseData());
    }

    #[DataProviderExternal(RefusedEmails::class, 'cases')]
    public function testForgotPasswordRejectsAnEmailTheValueObjectRefuses(string $email): void
    {
        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => $email,
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('Invalid email format', Json::at($this->getResponseData(), 'error', 'details', 'email'));
    }
}
