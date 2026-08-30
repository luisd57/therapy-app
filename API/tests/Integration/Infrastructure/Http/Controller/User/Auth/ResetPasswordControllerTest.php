<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\Email;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\KeepsBlocklistAcrossRequests;

final class ResetPasswordControllerTest extends ApiTestCase
{
    use KeepsBlocklistAcrossRequests;

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

    /**
     * A reset is how a user reacts to a stolen session, so the sessions that
     * existed before it must stop working.
     */
    public function testResetInvalidatesSessionsIssuedBeforeIt(): void
    {
        // Real clock on purpose: iat comes from Lexik's time(), not ClockInterface,
        // so freezing the clock would desync it from the cutoff.
        $this->useBlocklistThatSurvivesRequests();

        $email = 'reset-revokes-sessions@test.com';
        $jwt = $this->createTherapistAndGetToken(email: $email, password: 'Password1!');

        $this->jsonRequest('GET', '/api/auth/me', [], $jwt);
        $this->assertResponseIsSuccessful();

        $rawResetToken = $this->issueResetTokenFor($email);

        $this->jsonRequest('POST', '/api/auth/password/reset', [
            'token' => $rawResetToken,
            'password' => 'BrandNewPass1!',
        ]);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/auth/me', [], $jwt);
        $this->assertResponseStatusCodeSame(401);
    }

    private function issueResetTokenFor(string $email): string
    {
        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail(Email::fromString($email));
        $raw = self::getContainer()->get(TokenGeneratorInterface::class)->generate();

        self::getContainer()->get(PasswordResetTokenRepositoryInterface::class)->save(
            DomainTestHelper::createValidPasswordResetToken(token: $raw, user: $user),
        );

        return $raw;
    }
}
