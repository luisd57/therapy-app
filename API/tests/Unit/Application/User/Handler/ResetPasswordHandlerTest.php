<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\ResetPasswordInputDTO;
use App\Application\User\Handler\ResetPasswordHandler;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\JwtBlocklistInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ResetPasswordHandlerTest extends TestCase
{
    private PasswordResetTokenRepositoryInterface&MockObject $resetTokenRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private JwtBlocklistInterface&MockObject $jwtBlocklist;
    private ClockInterface&MockObject $clock;
    private ResetPasswordHandler $handler;

    protected function setUp(): void
    {
        $this->resetTokenRepository = $this->createMock(PasswordResetTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->jwtBlocklist = $this->createMock(JwtBlocklistInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());

        $this->handler = $this->makeHandler($this->jwtBlocklist, $this->clock);
    }

    private function makeHandler(JwtBlocklistInterface $jwtBlocklist, ClockInterface $clock): ResetPasswordHandler
    {
        return new ResetPasswordHandler(
            $this->resetTokenRepository,
            $this->userRepository,
            $this->passwordHasher,
            $jwtBlocklist,
            $clock,
            jwtTokenTtl: 3600,
        );
    }

    public function testHandleSuccessUpdatesPasswordAndMarksTokenUsed(): void
    {
        $user = DomainTestHelper::createReconstitutedActivePatient();
        $resetToken = DomainTestHelper::createValidPasswordResetToken(
            token: 'valid-reset',
            user: $user,
        );

        $this->resetTokenRepository->method('findByToken')->willReturn($resetToken);
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

    public function testResetRevokesEverySessionIssuedUpToTheResetInstant(): void
    {
        // 2026-08-21T10:00:00Z as a literal, so the assertion does not recompute
        // the timestamp the same way the handler does.
        $resetAt = new \DateTimeImmutable('2026-08-21T10:00:00+00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($resetAt);

        $jwtBlocklist = $this->createMock(JwtBlocklistInterface::class);
        $handler = $this->makeHandler($jwtBlocklist, $clock);

        $user = DomainTestHelper::createReconstitutedActivePatient();
        $resetToken = DomainTestHelper::createValidPasswordResetToken(token: 'valid-reset', user: $user);

        $this->resetTokenRepository->method('findByToken')->willReturn($resetToken);
        $this->passwordHasher->method('hash')->willReturn('new_hashed_pw');

        $jwtBlocklist->expects($this->once())
            ->method('revokeIssuedAtOrBefore')
            ->with($user->getEmail()->getValue(), 1787306400, 3600);

        $handler->__invoke(new ResetPasswordInputDTO(token: 'valid-reset', newPassword: 'newpass123'));
    }
}
