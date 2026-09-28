<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\Handler\ValidateInvitationHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ValidateInvitationHandlerTest extends TestCase
{
    use UsesUtcInstants;

    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private ClockInterface&MockObject $clock;
    private ValidateInvitationHandler $handler;

    protected function setUp(): void
    {
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(self::utc('2026-06-15 12:00:00'));
        $this->handler = new ValidateInvitationHandler($this->invitationRepository, $this->clock);
    }

    public function testHandleValidTokenReturnsInvitationOutputDTO(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            token: 'valid-token',
            email: 'patient@example.com',
            patientName: 'Test Patient',
            now: self::utc('2026-06-15 11:00:00'),
        );

        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $result = $this->handler->__invoke('valid-token');

        $this->assertSame('patient@example.com', $result->email);
        $this->assertSame('Test Patient', $result->patientName);
        $this->assertSame('pending', $result->status);
    }

    public function testHandleTokenNotFoundThrowsInvalidTokenException(): void
    {
        $this->invitationRepository->method('findByToken')->willReturn(null);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke('nonexistent-token');
    }

    public function testHandleTokenAlreadyUsedThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke('used-token');
    }

    public function testHandleTokenExpiredThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation(now: self::utc('2026-06-15 12:00:00'));
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke('expired-token');
    }

    public function testHandleRevokedTokenThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createRevokedInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('Token has been revoked.');

        $this->handler->__invoke('revoked-token');
    }
}
