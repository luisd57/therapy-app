<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\TherapistLoginInputDTO;
use App\Application\User\Handler\JwtTokenGeneratorInterface;
use App\Application\User\Handler\TherapistLoginHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TherapistLoginHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private JwtTokenGeneratorInterface&MockObject $jwtTokenGenerator;
    private TherapistLoginHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);
        $this->handler = new TherapistLoginHandler(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtTokenGenerator,
        );
    }

    public function testLoginSuccess(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();

        $this->userRepository->method('findByEmail')->willReturn($therapist);
        $this->passwordHasher->method('verify')->willReturn(true);
        $this->jwtTokenGenerator->method('generate')->willReturn('jwt-token-123');

        $result = $this->handler->__invoke(new TherapistLoginInputDTO('therapist@example.com', 'password'));

        $this->assertSame('jwt-token-123', $result->token);
        $this->assertSame('therapist@example.com', $result->user->email);
        $this->assertSame('ROLE_THERAPIST', $result->user->role);
    }

    public function testUserNotFoundThrowsInvalidCredentials(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->__invoke(new TherapistLoginInputDTO('unknown@example.com', 'password'));
    }

    public function testWrongRoleThrowsInvalidCredentials(): void
    {
        $patient = DomainTestHelper::createReconstitutedActivePatient();
        $this->userRepository->method('findByEmail')->willReturn($patient);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->__invoke(new TherapistLoginInputDTO('patient@example.com', 'password'));
    }

    public function testInactiveUserThrowsUserNotActive(): void
    {
        $inactiveTherapist = \App\Domain\User\Entity\User::reconstitute(
            id: \App\Domain\User\ValueObject\UserId::generate(),
            email: \App\Domain\User\ValueObject\Email::fromString('inactive@example.com'),
            fullName: 'Inactive Therapist',
            role: \App\Domain\User\ValueObject\UserRole::THERAPIST,
            password: 'hashed',
            phone: null,
            address: null,
            isActive: false,
            createdAt: new \DateTimeImmutable(),
            activatedAt: null,
            updatedAt: new \DateTimeImmutable(),
        );

        $this->userRepository->method('findByEmail')->willReturn($inactiveTherapist);

        $this->expectException(UserNotActiveException::class);
        $this->handler->__invoke(new TherapistLoginInputDTO('inactive@example.com', 'password'));
    }

    public function testWrongPasswordThrowsInvalidCredentials(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();
        $this->userRepository->method('findByEmail')->willReturn($therapist);
        $this->passwordHasher->method('verify')->willReturn(false);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->__invoke(new TherapistLoginInputDTO('therapist@example.com', 'wrong-password'));
    }
}
