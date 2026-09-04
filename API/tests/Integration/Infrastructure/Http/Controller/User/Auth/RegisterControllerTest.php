<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\SeedsAuthFixtures;

final class RegisterControllerTest extends ApiTestCase
{
    use SeedsAuthFixtures;

    public function testRegisterValidTokenReturns201(): void
    {
        $invitation = $this->seedInvitation();

        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => $invitation->getToken(),
            'password' => 'Secure1!pass',
            'password_confirmation' => 'Secure1!pass',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ROLE_PATIENT', $data['data']['user']['role']);
    }

    public function testRegisterMissingTokenReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'password' => 'Secure1!pass',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterShortPasswordReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => 'some-token',
            'password' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Everything else about this request is valid, so the response can only be about the password
     * rule it breaks. The status code alone would still pass with PasswordStrength unwired.
     */
    public function testRegisterReportsWhichPasswordRuleFailed(): void
    {
        $invitation = $this->seedInvitation();

        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => $invitation->getToken(),
            'password' => 'Secure1pass',
            'password_confirmation' => 'Secure1pass',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertSame(
            'Password must contain at least one special character',
            $data['error']['details']['password'],
        );
        $this->assertArrayNotHasKey('token', $data['error']['details']);
        $this->assertArrayNotHasKey('password_confirmation', $data['error']['details']);
    }
}
