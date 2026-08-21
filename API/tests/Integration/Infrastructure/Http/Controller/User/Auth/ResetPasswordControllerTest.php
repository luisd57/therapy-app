<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\User\Auth;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Service\JwtBlocklistInterface;
use App\Infrastructure\Security\RedisJwtBlocklist;
use App\Tests\Helper\ApiTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

final class ResetPasswordControllerTest extends ApiTestCase
{
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

    /**
     * The test container's cache.app is an ArrayAdapter that Symfony resets between
     * requests, so a cutoff written by one request is gone by the next. Prod runs a
     * RedisAdapter, which does not. Same class under test, storage that persists.
     */
    private function useBlocklistThatSurvivesRequests(): void
    {
        $pool = new FilesystemAdapter('jwt-blocklist-test', 0, sys_get_temp_dir());
        $pool->clear();

        self::getContainer()->set(JwtBlocklistInterface::class, new RedisJwtBlocklist($pool));
    }

    private function issueResetTokenFor(string $email): string
    {
        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail(Email::fromString($email));
        $raw = self::getContainer()->get(TokenGeneratorInterface::class)->generate();

        self::getContainer()->get(PasswordResetTokenRepositoryInterface::class)->save(
            PasswordResetToken::create(
                id: TokenId::generate(),
                token: $raw,
                userId: $user->getId(),
                ttlSeconds: 3600,
                now: new \DateTimeImmutable(),
            ),
        );

        return $raw;
    }
}
