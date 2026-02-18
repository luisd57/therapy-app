<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Application\User\Handler\ResetPasswordHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordHandlerTest extends TestCase
{
    private PasswordResetTokenRepositoryInterface&MockObject $resetTokenRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private ResetPasswordHandler $handler;

    protected function setUp(): void
    {
        $this->resetTokenRepository = $this->createMock(PasswordResetTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->handler = new ResetPasswordHandler(
            $this->resetTokenRepository,
            $this->userRepository,
            $this->passwordHasher,
        );
    }

    public function testHandleSuccessUpdatesPasswordAndMarksTokenUsed(): void
    {
        $userId = \App\Domain\User\ValueObject\UserId::generate();
        $resetToken = DomainTestHelper::createValidPasswordResetToken(
            token: 'valid-reset',
            userId: $userId,
        );
        $user = DomainTestHelper::createReconstitutedActivePatient(id: $userId);

        $this->resetTokenRepository->method('findByToken')->willReturn($resetToken);
        $this->userRepository->method('findById')->willReturn($user);
        $this->passwordHasher->method('hash')->willReturn('new_hashed_pw');
        $this->userRepository->expects($this->once())->method('save');
        $this->resetTokenRepository->expects($this->once())->method('save');

        $this->handler->__invoke(new ResetPasswordInputDTO(token: 'valid-reset', newPassword: 'newpass123'));

        $this->assertSame('new_hashed_pw', $user->getPassword());
    }

    public function testHandleTokenNotFoundThrowsInvalidTokenException(): void
    {
        $this->resetTokenRepository->method('findByToken')->willReturn(null);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke(new ResetPasswordInputDTO(token: 'bad', newPassword: 'pass'));
    }

    public function testHandleTokenAlreadyUsedThrowsInvalidTokenException(): void
    {
        $token = DomainTestHelper::createUsedPasswordResetToken();
        $this->resetTokenRepository->method('findByToken')->willReturn($token);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke(new ResetPasswordInputDTO(token: 'used', newPassword: 'pass'));
    }

    public function testHandleTokenExpiredThrowsInvalidTokenException(): void
    {
        $token = DomainTestHelper::createExpiredPasswordResetToken();
        $this->resetTokenRepository->method('findByToken')->willReturn($token);

        $this->expectException(InvalidTokenException::class);
        $this->handler->__invoke(new ResetPasswordInputDTO(token: 'expired', newPassword: 'pass'));
    }

    public function testHandleUserNotFoundThrowsUserNotFoundException(): void
    {
        $resetToken = DomainTestHelper::createValidPasswordResetToken();
        $this->resetTokenRepository->method('findByToken')->willReturn($resetToken);
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->handler->__invoke(new ResetPasswordInputDTO(token: 'valid', newPassword: 'pass'));
    }
}
