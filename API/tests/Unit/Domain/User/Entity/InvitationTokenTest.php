<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\Id\TokenId;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * InvitationToken takes now as an ordinary argument, so time here is a literal rather
 * than a frozen clock the entity never consults. See ADR-0003.
 */
final class InvitationTokenTest extends TestCase
{
    use UsesUtcInstants;

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

    public function testInvitationHoldsTheTherapistWhoSentIt(): void
    {
        $therapist = DomainTestHelper::createTherapist();
        $invitation = DomainTestHelper::createValidInvitation(invitedBy: $therapist);

        $this->assertSame($therapist, $invitation->getInvitedBy());
        $this->assertTrue($therapist->getId()->equals($invitation->getInvitedById()));
    }

    public function testCreateSetsExpiresAtToNowPlusTheTtl(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            ttlSeconds: 86400,
            now: new DateTimeImmutable('2026-05-01T12:00:00+00:00'),
        );

        self::assertInstantIs('2026-05-01T12:00:00+00:00', $invitation->getCreatedAt());
        self::assertInstantIs('2026-05-02T12:00:00+00:00', $invitation->getExpiresAt());
    }

    /** The TTL runs from the instant given, so an offset in it must carry through. */
    public function testCreateCountsTheTtlFromTheInstantItIsGiven(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            ttlSeconds: 3600,
            now: new DateTimeImmutable('2026-05-01T08:00:00-04:00'),
        );

        self::assertInstantIs('2026-05-01T13:00:00+00:00', $invitation->getExpiresAt());
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

    /**
     * isExpired compares with `<`, so the expiry instant is still valid and the second
     * after it is not. A wall-clock expiry makes that depend on how long the test ran.
     */
    public function testATokenIsValidAtItsExpiryInstantAndExpiredAfterIt(): void
    {
        $expiresAt = new DateTimeImmutable('2026-05-01T12:00:00+00:00');
        $invitation = DomainTestHelper::createBoundaryInvitation(expiresAt: $expiresAt);

        $this->assertFalse($invitation->isExpired($expiresAt));
        $this->assertTrue($invitation->isValid($expiresAt));

        $aSecondLater = new DateTimeImmutable('2026-05-01T12:00:01+00:00');
        $this->assertTrue($invitation->isExpired($aSecondLater));
        $this->assertFalse($invitation->isValid($aSecondLater));
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
        $invitedBy = DomainTestHelper::createTherapist();
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
        $this->assertTrue($invitedBy->getId()->equals($invitation->getInvitedById()));
        $this->assertTrue($invitation->isUsed());
        $this->assertSame($createdAt, $invitation->getCreatedAt());
        $this->assertSame($expiresAt, $invitation->getExpiresAt());
        $this->assertSame($usedAt, $invitation->getUsedAt());
    }
}
