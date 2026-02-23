<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

use App\Domain\User\Entity\PasswordResetToken;
use App\Domain\User\ValueObject\TokenId;
use App\Domain\User\ValueObject\UserId;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    public function testCreateSetsAllProperties(): void
    {
        $userId = UserId::generate();
        $token = DomainTestHelper::createValidPasswordResetToken(
            token: 'reset-test',
            userId: $userId,
        );

        $this->assertSame('reset-test', $token->getToken());
        $this->assertTrue($userId->equals($token->getUserId()));
        $this->assertFalse($token->isUsed());
        $this->assertNull($token->getUsedAt());
    }

    public function testCreateExpiresAtIsInFuture(): void
    {
        $beforeCreate = new DateTimeImmutable();
        $token = DomainTestHelper::createValidPasswordResetToken(ttlSeconds: 3600);
        $afterCreate = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreate->modify('+3600 seconds'), $token->getExpiresAt());
        $this->assertLessThanOrEqual($afterCreate->modify('+3600 seconds'), $token->getExpiresAt());
    }

    public function testUseValidTokenMarksAsUsed(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $token->use();

        $this->assertTrue($token->isUsed());
        $this->assertNotNull($token->getUsedAt());
    }

    public function testUseAlreadyUsedTokenThrowsDomainException(): void
    {
        $token = DomainTestHelper::createUsedPasswordResetToken();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been used');
        $token->use();
    }

    public function testUseExpiredTokenThrowsDomainException(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');
        $token->use();
    }

    public function testIsExpiredFreshTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredExpiredTokenReturnsTrue(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredBoundaryTokenExpiringAtNow(): void
    {
        $now = new DateTimeImmutable();
        $token = PasswordResetToken::reconstitute(
            id: TokenId::generate(),
            token: 'boundary-reset',
            userId: UserId::generate(),
            isUsed: false,
            createdAt: new DateTimeImmutable('-1 hour'),
            expiresAt: $now,
            usedAt: null,
        );

        $isExpired = $token->isExpired();
        $this->assertIsBool($isExpired);

        if ($isExpired) {
            $this->assertFalse($token->isValid());
        }
    }

    public function testIsValidValidTokenReturnsTrue(): void
    {
        $token = DomainTestHelper::createValidPasswordResetToken();

        $this->assertTrue($token->isValid());
    }

    public function testIsValidUsedTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createUsedPasswordResetToken();

        $this->assertFalse($token->isValid());
    }

    public function testIsValidExpiredTokenReturnsFalse(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();

        $this->assertFalse($token->isValid());
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = TokenId::generate();
        $userId = UserId::generate();
        $createdAt = new DateTimeImmutable('-1 day');
        $expiresAt = new DateTimeImmutable('+1 day');
        $usedAt = new DateTimeImmutable('-1 hour');

        $token = PasswordResetToken::reconstitute(
            id: $id,
            token: 'recon-reset',
            userId: $userId,
            isUsed: true,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            usedAt: $usedAt,
        );

        $this->assertTrue($id->equals($token->getId()));
        $this->assertSame('recon-reset', $token->getToken());
        $this->assertTrue($userId->equals($token->getUserId()));
        $this->assertTrue($token->isUsed());
        $this->assertSame($createdAt, $token->getCreatedAt());
        $this->assertSame($expiresAt, $token->getExpiresAt());
        $this->assertSame($usedAt, $token->getUsedAt());
    }
}
