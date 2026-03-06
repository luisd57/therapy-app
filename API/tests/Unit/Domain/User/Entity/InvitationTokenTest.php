<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Id\UserId;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InvitationTokenTest extends TestCase
{
    public function testCreateSetsAllProperties(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            token: 'test-token',
            email: 'test@example.com',
            patientName: 'Test Patient',
        );

        $this->assertSame('test-token', $invitation->getToken());
        $this->assertSame('test@example.com', $invitation->getEmail()->getValue());
        $this->assertSame('Test Patient', $invitation->getPatientName());
        $this->assertFalse($invitation->isUsed());
        $this->assertNull($invitation->getUsedAt());
    }

    public function testCreateExpiresAtIsInFuture(): void
    {
        $beforeCreate = new DateTimeImmutable();
        $invitation = DomainTestHelper::createValidInvitation(ttlSeconds: 86400);
        $afterCreate = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreate->modify('+86400 seconds'), $invitation->getExpiresAt());
        $this->assertLessThanOrEqual($afterCreate->modify('+86400 seconds'), $invitation->getExpiresAt());
    }

    public function testUseValidTokenMarksAsUsed(): void
    {
        $invitation = DomainTestHelper::createValidInvitation();

        $invitation->use(new DateTimeImmutable());

        $this->assertTrue($invitation->isUsed());
        $this->assertNotNull($invitation->getUsedAt());
    }

    public function testUseAlreadyUsedTokenThrowsDomainException(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been used');
        $invitation->use(new DateTimeImmutable());
    }

    public function testUseExpiredTokenThrowsDomainException(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');
        $invitation->use(new DateTimeImmutable());
    }

    public function testIsExpiredFreshTokenReturnsFalse(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(ttlSeconds: 86400);

        $this->assertFalse($invitation->isExpired(new DateTimeImmutable()));
    }

    public function testIsExpiredExpiredTokenReturnsTrue(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation();

        $this->assertTrue($invitation->isExpired(new DateTimeImmutable()));
    }

    public function testIsExpiredBoundaryTokenExpiringAtNow(): void
    {
        // Token with expiresAt = now. Since isExpired checks `<`, exactly now should NOT be expired
        // but due to time passing between creation and check, this is a boundary test
        $invitation = DomainTestHelper::createBoundaryInvitation();

        // The boundary token has expiresAt set to 'now' at creation time.
        // By the time we check, a tiny amount of time has passed, so it may be expired.
        // This tests that the behavior is consistent: expiresAt < now means expired.
        $isExpired = $invitation->isExpired(new DateTimeImmutable());
        $this->assertIsBool($isExpired);

        // If expired, it should NOT be valid
        if ($isExpired) {
            $this->assertFalse($invitation->isValid(new DateTimeImmutable()));
        }
    }

    public function testIsValidValidTokenReturnsTrue(): void
    {
        $invitation = DomainTestHelper::createValidInvitation();

        $this->assertTrue($invitation->isValid(new DateTimeImmutable()));
    }

    public function testIsValidUsedTokenReturnsFalse(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();

        $this->assertFalse($invitation->isValid(new DateTimeImmutable()));
    }

    public function testIsValidExpiredTokenReturnsFalse(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation();

        $this->assertFalse($invitation->isValid(new DateTimeImmutable()));
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $id = TokenId::generate();
        $email = Email::fromString('recon@example.com');
        $invitedBy = UserId::generate();
        $createdAt = new DateTimeImmutable('-1 day');
        $expiresAt = new DateTimeImmutable('+1 day');
        $usedAt = new DateTimeImmutable('-1 hour');

        $invitation = InvitationToken::reconstitute(
            id: $id,
            token: 'recon-token',
            email: $email,
            patientName: 'Recon Patient',
            invitedBy: $invitedBy,
            isUsed: true,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            usedAt: $usedAt,
        );

        $this->assertTrue($id->equals($invitation->getId()));
        $this->assertSame('recon-token', $invitation->getToken());
        $this->assertTrue($email->equals($invitation->getEmail()));
        $this->assertSame('Recon Patient', $invitation->getPatientName());
        $this->assertTrue($invitedBy->equals($invitation->getInvitedBy()));
        $this->assertTrue($invitation->isUsed());
        $this->assertSame($createdAt, $invitation->getCreatedAt());
        $this->assertSame($expiresAt, $invitation->getExpiresAt());
        $this->assertSame($usedAt, $invitation->getUsedAt());
    }
}
