<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\DTO\Input\ActivatePatientInputDTO;
use App\Application\User\Handler\ActivatePatientHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ActivatePatientHandlerTest extends TestCase
{
    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private EmailSenderInterface&MockObject $emailSender;
    private ActivatePatientHandler $handler;

    protected function setUp(): void
    {
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->emailSender = $this->createMock(EmailSenderInterface::class);

        $this->handler = new ActivatePatientHandler(
            $this->invitationRepository,
            $this->userRepository,
            $this->passwordHasher,
            $this->emailSender,
        );
    }

    public function testHandleSuccessCreatesAndActivatesPatient(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            token: 'valid-token',
            email: 'newpatient@example.com',
            patientName: 'New Patient',
        );

        $this->invitationRepository->method('findByToken')->willReturn($invitation);
        $this->passwordHasher->method('hash')->willReturn('hashed_pw');
        $this->userRepository->expects($this->once())->method('save');
        $this->invitationRepository->expects($this->once())->method('save');
        $this->emailSender->expects($this->once())->method('sendWelcome');

        $input = new ActivatePatientInputDTO(token: 'valid-token', password: 'securepass');
        $result = $this->handler->handle($input);

        $this->assertSame('newpatient@example.com', $result->email);
        $this->assertSame('New Patient', $result->fullName);
        $this->assertSame('ROLE_PATIENT', $result->role);
        $this->assertTrue($result->isActive);
    }

    public function testHandleTokenNotFoundThrowsInvalidTokenException(): void
    {
        $this->invitationRepository->method('findByToken')->willReturn(null);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle(new ActivatePatientInputDTO(token: 'bad-token', password: 'pass'));
    }

    public function testHandleTokenAlreadyUsedThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle(new ActivatePatientInputDTO(token: 'used-token', password: 'pass'));
    }

    public function testHandleTokenExpiredThrowsInvalidTokenException(): void
    {
        $invitation = DomainTestHelper::createExpiredInvitation();
        $this->invitationRepository->method('findByToken')->willReturn($invitation);

        $this->expectException(InvalidTokenException::class);
        $this->handler->handle(new ActivatePatientInputDTO(token: 'expired-token', password: 'pass'));
    }

    public function testHandleSuccessSendsWelcomeEmail(): void
    {
        $invitation = DomainTestHelper::createValidInvitation(
            email: 'welcome@example.com',
            patientName: 'Welcome Patient',
        );

        $this->invitationRepository->method('findByToken')->willReturn($invitation);
        $this->passwordHasher->method('hash')->willReturn('hashed');

        $this->emailSender
            ->expects($this->once())
            ->method('sendWelcome')
            ->with(
                $this->callback(fn($email) => $email->getValue() === 'welcome@example.com'),
                'Welcome Patient',
            );

        $input = new ActivatePatientInputDTO(token: 'token', password: 'securepass');
        $this->handler->handle($input);
    }
}
