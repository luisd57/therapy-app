<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\RequestPasswordResetInputDTO;
use App\Application\User\Handler\RequestPasswordResetHandler;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Tests\Helper\DomainTestHelper;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RequestPasswordResetHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordResetTokenRepositoryInterface&MockObject $resetTokenRepository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private EmailSenderInterface&MockObject $emailSender;
    private ClockInterface&MockObject $clock;
    private RequestPasswordResetHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->resetTokenRepository = $this->createMock(PasswordResetTokenRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $this->emailSender = $this->createMock(EmailSenderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->handler = new RequestPasswordResetHandler(
            $this->userRepository,
            $this->resetTokenRepository,
            $this->tokenGenerator,
            $this->emailSender,
            'http://localhost:3000',
            3600,
            $this->clock,
        );
    }

    public function testHandleExistingActiveUserCreatesTokenAndSendsEmail(): void
    {
        $user = DomainTestHelper::createReconstitutedActivePatient(email: 'active@example.com');

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->tokenGenerator->method('generate')->willReturn('reset-token');
        $this->resetTokenRepository->expects($this->once())->method('invalidateAllForUser');
        $this->resetTokenRepository->expects($this->once())->method('save');
        $this->emailSender->expects($this->once())->method('sendPasswordReset');

        $this->handler->__invoke(new RequestPasswordResetInputDTO(email: 'active@example.com'));
    }

    public function testHandleNonExistentUserSilentlyReturnsNoEmailSent(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->emailSender->expects($this->never())->method('sendPasswordReset');
        $this->resetTokenRepository->expects($this->never())->method('save');

        // Should NOT throw any exception
        $this->handler->__invoke(new RequestPasswordResetInputDTO(email: 'nonexistent@example.com'));
    }

    public function testHandleInactiveUserSilentlyReturnsNoEmailSent(): void
    {
        $inactiveUser = DomainTestHelper::createReconstitutedInactivePatient(email: 'inactive@example.com');

        $this->userRepository->method('findByEmail')->willReturn($inactiveUser);
        $this->emailSender->expects($this->never())->method('sendPasswordReset');
        $this->resetTokenRepository->expects($this->never())->method('save');

        // Should NOT throw any exception
        $this->handler->__invoke(new RequestPasswordResetInputDTO(email: 'inactive@example.com'));
    }

    public function testHandleInvalidatesOldTokensBeforeCreatingNew(): void
    {
        $user = DomainTestHelper::createReconstitutedActivePatient();

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->tokenGenerator->method('generate')->willReturn('new-token');

        $this->resetTokenRepository
            ->expects($this->once())
            ->method('invalidateAllForUser')
            ->with($this->callback(fn($id) => $id->equals($user->getId())));

        $this->handler->__invoke(new RequestPasswordResetInputDTO(email: 'patient@example.com'));
    }

    public function testHandleGeneratesCorrectResetUrl(): void
    {
        $user = DomainTestHelper::createReconstitutedActivePatient();

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->tokenGenerator->method('generate')->willReturn('my-reset-token');

        $this->emailSender
            ->expects($this->once())
            ->method('sendPasswordReset')
            ->with(
                $this->anything(),
                $this->stringContains('http://localhost:3000/reset-password?token=my-reset-token'),
            );

        $this->handler->__invoke(new RequestPasswordResetInputDTO(email: 'patient@example.com'));
    }
}
