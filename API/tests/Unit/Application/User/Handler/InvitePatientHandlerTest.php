<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\InvitePatientInputDTO;
use App\Application\User\Handler\InvitePatientHandler;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Domain\User\ValueObject\UserId;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class InvitePatientHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private EmailSenderInterface&MockObject $emailSender;
    private InvitePatientHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $this->emailSender = $this->createMock(EmailSenderInterface::class);

        $this->handler = new InvitePatientHandler(
            $this->userRepository,
            $this->invitationRepository,
            $this->tokenGenerator,
            $this->emailSender,
            'http://localhost:3000',
            86400,
        );
    }

    public function testHandleSuccessCreatesInvitationAndSendsEmail(): void
    {
        $this->userRepository->method('existsByEmail')->willReturn(false);
        $this->invitationRepository->method('findValidByEmail')->willReturn(null);
        $this->tokenGenerator->method('generate')->willReturn('generated-token');
        $this->invitationRepository->expects($this->once())->method('save');
        $this->emailSender->expects($this->once())->method('sendInvitation');

        $input = new InvitePatientInputDTO(
            email: 'newpatient@example.com',
            patientName: 'New Patient',
            therapistId: UserId::generate()->getValue(),
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('newpatient@example.com', $result->email);
        $this->assertSame('New Patient', $result->patientName);
        $this->assertSame('pending', $result->status);
    }

    public function testHandleUserAlreadyExistsThrowsException(): void
    {
        $this->userRepository->method('existsByEmail')->willReturn(true);

        $input = new InvitePatientInputDTO(
            email: 'existing@example.com',
            patientName: 'Existing Patient',
            therapistId: UserId::generate()->getValue(),
        );

        $this->expectException(UserAlreadyExistsException::class);
        $this->handler->__invoke($input);
    }

    public function testHandleExistingValidInvitationReturnsExistingDTO(): void
    {
        $existingInvitation = DomainTestHelper::createValidInvitation(
            email: 'patient@example.com',
            patientName: 'Already Invited',
        );

        $this->userRepository->method('existsByEmail')->willReturn(false);
        $this->invitationRepository->method('findValidByEmail')->willReturn($existingInvitation);
        $this->invitationRepository->expects($this->never())->method('save');
        $this->emailSender->expects($this->never())->method('sendInvitation');

        $input = new InvitePatientInputDTO(
            email: 'patient@example.com',
            patientName: 'Already Invited',
            therapistId: UserId::generate()->getValue(),
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('patient@example.com', $result->email);
    }

    public function testHandleSuccessGeneratesCorrectRegistrationUrl(): void
    {
        $this->userRepository->method('existsByEmail')->willReturn(false);
        $this->invitationRepository->method('findValidByEmail')->willReturn(null);
        $this->tokenGenerator->method('generate')->willReturn('my-token');

        $this->emailSender
            ->expects($this->once())
            ->method('sendInvitation')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->stringContains('http://localhost:3000/register?token=my-token'),
            );

        $input = new InvitePatientInputDTO(
            email: 'patient@example.com',
            patientName: 'Patient',
            therapistId: UserId::generate()->getValue(),
        );

        $this->handler->__invoke($input);
    }
}
