<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\User;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
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
            'password' => 'Password1!',
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
            'password' => 'Password1!',
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
            'password' => 'Password1!',
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
            'password' => 'NewPass1!',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testResetPasswordInvalidTokenReturns400(): void
    {
        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'token' => 'bad-token',
            'password' => 'NewPass1!',
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

    // ── HttpOnly Cookie Tests ─────────────────────────────────────────

    public function testLoginSetsHttpOnlyCookie(): void
    {
        $this->seedTherapist();

        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'therapist@test.com', 'password' => 'Password1!']));

        $this->assertResponseIsSuccessful();

        $cookie = $this->client->getCookieJar()->get('THERAPY_JWT', '/api');
        $this->assertNotNull($cookie, 'Login should set THERAPY_JWT cookie');
        $this->assertNotEmpty($cookie->getValue());

        // Verify response body does not contain the token
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('token', $data['data']);
        $this->assertArrayHasKey('user', $data['data']);
    }

    public function testCookieAuthenticatesOnProtectedEndpoint(): void
    {
        $this->seedTherapist();

        // Login to get the cookie
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'therapist@test.com', 'password' => 'Password1!']));

        $this->assertResponseIsSuccessful();

        // Access protected endpoint — cookie is sent automatically by the cookie jar
        $this->client->request('GET', '/api/therapist/me');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('therapist@test.com', $data['data']['email']);
    }

    public function testLogoutClearsCookie(): void
    {
        $token = $this->createTherapistAndGetToken();

        $this->jsonRequest('POST', '/api/auth/logout', [], $token);

        $this->assertResponseIsSuccessful();

        // Verify the cookie is expired
        $cookie = $this->client->getCookieJar()->get('THERAPY_JWT', '/api');
        $this->assertTrue(
            $cookie === null || $cookie->isExpired(),
            'THERAPY_JWT cookie should be expired after logout',
        );
    }

    public function testLogoutWorksWithCookieToken(): void
    {
        $this->seedTherapist();

        // Login to get the cookie
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'therapist@test.com', 'password' => 'Password1!']));

        // Logout using only the cookie (no Authorization header)
        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertSame('Successfully logged out.', $data['data']['message']);
    }

    public function testAuthMeWithValidCookieReturns200(): void
    {
        $this->seedTherapist();

        // Login to set the cookie
        $this->client->request('POST', '/api/auth/therapist/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => 'therapist@test.com', 'password' => 'Password1!']));

        // Call /auth/me — cookie sent automatically
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseIsSuccessful();
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame('therapist@test.com', $data['data']['email']);
        $this->assertSame('ROLE_THERAPIST', $data['data']['role']);
    }

    public function testAuthMeWithoutCookieReturns401(): void
    {
        $this->jsonRequest('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testBearerTokenStillWorksOnProtectedEndpoints(): void
    {
        $token = $this->createTherapistAndGetToken();

        // Clear the cookie jar so only Bearer is used
        $this->client->getCookieJar()->expire('THERAPY_JWT', '/api');

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
            hashedPassword: $hasher->hash('Password1!'),
            now: new \DateTimeImmutable(),
        );
        $repo->save($therapist);
    }

    private function seedInvitation(): InvitationToken
    {
        $therapist = User::createTherapist(
            id: UserId::generate(),
            email: Email::fromString('inviter-' . bin2hex(random_bytes(4)) . '@test.com'),
            fullName: 'Inviter Therapist',
            hashedPassword: 'hashed_password',
            now: new \DateTimeImmutable(),
        );
        self::getContainer()->get(UserRepositoryInterface::class)->save($therapist);

        $invitation = InvitationToken::create(
            id: TokenId::generate(),
            token: 'test-invitation-token-' . bin2hex(random_bytes(8)),
            email: Email::fromString('newpatient@test.com'),
            patientName: 'New Patient',
            invitedBy: $therapist->getId(),
            ttlSeconds: 86400,
            now: new \DateTimeImmutable(),
        );

        $repo = self::getContainer()->get(InvitationTokenRepositoryInterface::class);
        $repo->save($invitation);

        return $invitation;
    }
}
