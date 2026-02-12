<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Tests\Helper\ApiTestCase;

final class AuthControllerTest extends ApiTestCase
{
    public function testTherapistLoginSuccess(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
    }

    public function testTherapistLoginMissingEmailReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
    }

    public function testTherapistLoginWrongPasswordReturns401(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => 'therapist@test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testTherapistLoginNonExistentUserReturns401(): void
    {
        $this->jsonRequest('POST', '/api/auth/therapist/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPatientLoginSuccess(): void
    {
        $token = $this->createPatientAndGetToken();

        $this->assertNotEmpty($token);
        $this->assertResponseIsSuccessful();
    }

    public function testPatientLoginWrongRoleReturns401(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/patient/login', [
            'email' => 'therapist@test.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testValidateInvitationValidTokenReturns200(): void
    {
        $invitation = $this->seedInvitation();

        $this->client->request('GET', '/api/auth/invitation/validate/' . $invitation->getToken());

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
    }

    public function testValidateInvitationInvalidTokenReturns400(): void
    {
        $this->client->request('GET', '/api/auth/invitation/validate/nonexistent-token');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterValidTokenReturns201(): void
    {
        $invitation = $this->seedInvitation();

        $this->jsonRequest('POST', '/api/auth/register', [
            'token' => $invitation->getToken(),
            'password' => 'securepass123',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('ROLE_PATIENT', $data['data']['user']['role']);
    }

    public function testRegisterMissingTokenReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'password' => 'securepass123',
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

    public function testForgotPasswordExistingEmailReturns200(): void
    {
        $this->seedTherapist();

        $this->jsonRequest('POST', '/api/auth/password/forgot', [
            'email' => 'therapist@test.com',
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

    public function testResetPasswordMissingTokenReturns422(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'password' => 'newpassword123',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testResetPasswordInvalidTokenReturns400(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'token' => 'bad-token',
            'password' => 'newpassword123',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testFullLoginThenAccessProtectedResourceFlow(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Use the JWT token to access a protected endpoint
        $this->jsonRequest('GET', '/api/therapist/me', [], $token);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('therapist@test.com', $data['data']['email']);
    }

    private function seedTherapist(): void
    {
        $hasher = self::getContainer()->get(PasswordHasherInterface::class);
        $repo = self::getContainer()->get(UserRepositoryInterface::class);

        $therapist = User::createTherapist(
            id: UserId::generate(),
            email: Email::fromString('therapist@test.com'),
            fullName: 'Test Therapist',
            hashedPassword: $hasher->hash('password123'),
        );
        $repo->save($therapist);
    }

    private function seedInvitation(): InvitationToken
    {
        $invitation = InvitationToken::create(
            id: TokenId::generate(),
            token: 'test-invitation-token-' . bin2hex(random_bytes(8)),
            email: Email::fromString('newpatient@test.com'),
            patientName: 'New Patient',
            invitedBy: UserId::generate(),
            ttlSeconds: 86400,
        );

        $repo = self::getContainer()->get(InvitationTokenRepositoryInterface::class);
        $repo->save($invitation);

        return $invitation;
    }
}
