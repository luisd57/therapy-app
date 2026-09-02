<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\Id\TokenId;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * PasswordResetToken takes now as an ordinary argument, so time here is a literal
 * rather than a frozen clock the entity never consults. See ADR-0003.
 */
final class PasswordResetTokenTest extends TestCase
{
    use UsesUtcInstants;

    public function testCreateSetsAllProperties(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $token = DomainTestHelper::createValidPasswordResetToken(
            token: 'reset-test',
            user: $user,
        );

        $this->assertSame('reset-test', $token->getToken());
        $this->assertTrue($user->getId()->equals($token->getUserId()));
        $this->assertFalse($token->isUsed());
        $this->assertNull($token->getUsedAt());
    }

    public function testTokenHoldsTheUserItResets(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $token = DomainTestHelper::createValidPasswordResetToken(user: $user);

        $this->assertSame($user, $token->getUser());
    }

    public function testCreateSetsExpiresAtToNowPlusTheTtl(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(
            ttlSeconds: 3600,
            now: new DateTimeImmutable('2026-05-01T12:00:00+00:00'),
        );

        self::assertInstantIs('2026-05-01T12:00:00+00:00', $token->getCreatedAt());
        self::assertInstantIs('2026-05-01T13:00:00+00:00', $token->getExpiresAt());
    }

    /** The TTL runs from the instant given, so an offset in it must carry through. */
    public function testCreateCountsTheTtlFromTheInstantItIsGiven(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken(
            ttlSeconds: 3600,
            now: new DateTimeImmutable('2026-05-01T08:00:00-04:00'),
        );

        self::assertInstantIs('2026-05-01T13:00:00+00:00', $token->getExpiresAt());
    }

    public function testUseValidTokenMarksAsUsed(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $token->use(new DateTimeImmutable());

        $this->assertTrue($token->isUsed());
        $this->assertNotNull($token->getUsedAt());
    }

    public function testUseAlreadyUsedTokenThrowsDomainException(): void
    {
        $token = DomainTestHelper::createUsedPasswordResetToken();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been used');
        $token->use(new DateTimeImmutable());
    }

    public function testUseExpiredTokenThrowsDomainException(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');
        $token->use(new DateTimeImmutable());
    }

    public function testIsExpiredFreshTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $this->assertFalse($token->isExpired(new DateTimeImmutable()));
    }

    public function testIsExpiredExpiredTokenReturnsTrue(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->assertTrue($token->isExpired(new DateTimeImmutable()));
    }

    /**
     * isExpired compares with `<`, so the expiry instant is still valid and the second
     * after it is not. A wall-clock expiry makes that depend on how long the test ran.
     */
    public function testATokenIsValidAtItsExpiryInstantAndExpiredAfterIt(): void
    {
        $expiresAt = new DateTimeImmutable('2026-05-01T12:00:00+00:00');
        $token = PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: 'boundary-reset',
            user: DomainTestHelper::createActivePatient(),
            isUsed: false,
            createdAt: new DateTimeImmutable('2026-05-01T11:00:00+00:00'),
            expiresAt: $expiresAt,
            usedAt: null,
        );

        $this->assertFalse($token->isExpired($expiresAt));
        $this->assertTrue($token->isValid($expiresAt));

        $aSecondLater = new DateTimeImmutable('2026-05-01T12:00:01+00:00');
        $this->assertTrue($token->isExpired($aSecondLater));
        $this->assertFalse($token->isValid($aSecondLater));
    }

    public function testIsValidValidTokenReturnsTrue(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $this->assertTrue($token->isValid(new DateTimeImmutable()));
    }

    public function testIsValidUsedTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createUsedPasswordResetToken();

        $this->assertFalse($token->isValid(new DateTimeImmutable()));
    }

    public function testIsValidExpiredTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->assertFalse($token->isValid(new DateTimeImmutable()));
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = TokenId::generate();
        $user = DomainTestHelper::createActivePatient();
        $createdAt = new DateTimeImmutable('-1 day');
        $expiresAt = new DateTimeImmutable('+1 day');
        $usedAt = new DateTimeImmutable('-1 hour');

        $token = PasswordResetToken::reconstitute(
            id: $id,
            token: 'recon-reset',
            user: $user,
            isUsed: true,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            usedAt: $usedAt,
        );

        $this->assertTrue($id->equals($token->getId()));
        $this->assertSame('recon-reset', $token->getToken());
        $this->assertTrue($user->getId()->equals($token->getUserId()));
        $this->assertTrue($token->isUsed());
        $this->assertSame($createdAt, $token->getCreatedAt());
        $this->assertSame($expiresAt, $token->getExpiresAt());
        $this->assertSame($usedAt, $token->getUsedAt());
    }
}
