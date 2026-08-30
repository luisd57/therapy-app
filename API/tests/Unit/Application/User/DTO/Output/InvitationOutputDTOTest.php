<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\DTO\Output;

use App\Application\User\DTO\Output\InvitationOutputDTO;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Id\TokenId;
use App\Domain\User\ValueObject\Email;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InvitationOutputDTOTest extends TestCase
{
    public function testInstantsAreRenderedInUtcWhateverZoneTheyCarry(): void
    {
        $token = InvitationToken::reconstitute(
            id: TokenId::generate(),
            token: 'a-token',
            email: Email::fromString('patient@example.com'),
            patientName: 'Test Patient',
            invitedBy: DomainTestHelper::createTherapist(),
            isUsed: false,
            createdAt: new DateTimeImmutable('2026-06-01T09:00:00-04:00'),
            expiresAt: new DateTimeImmutable('2026-06-02T09:00:00-04:00'),
            usedAt: null,
        );

        $dto = InvitationOutputDTO::fromEntity($token, new DateTimeImmutable('2026-06-01T12:00:00+00:00'));

        $this->assertSame('2026-06-01T13:00:00+00:00', $dto->createdAt);
        $this->assertSame('2026-06-02T13:00:00+00:00', $dto->expiresAt);
    }
}
