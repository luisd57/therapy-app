<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\Handler\ValidateInvitationHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ValidateInvitationHandlerTest extends TestCase
{
    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private ValidateInvitationHandler $handler;

    protected function setUp(): void
    {
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->handler = new ValidateInvitationHandler($this->invitationRepository);
    }

    public function testHandleValidTokenReturnsInvitationDTO(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            token: 'valid-token',
            email: 'patient@example.com',
            patientName: 'Test Patient',
        );

        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $result = $this->handler->handle('valid-token');

        $this->assertSame('patient@example.com', $result->email);
        $this->assertSame('Test Patient', $result->patientName);
        $this->assertSame('pending', $result->status);
    }

    public function testHandleTokenNotFoundThrowsInvalidTokenException(): void
    {
        $this->invitationRepository->method('findByToken')->willReturn(null);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle('nonexistent-token');
    }

    public function testHandleTokenAlreadyUsedThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle('used-token');
    }

    public function testHandleTokenExpiredThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle('expired-token');
    }
}
