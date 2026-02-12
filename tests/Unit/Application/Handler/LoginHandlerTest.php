<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\Handler\JwtTokenGeneratorInterface;
use App\Application\User\Handler\LoginHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private JwtTokenGeneratorInterface&MockObject $jwtTokenGenerator;
    private LoginHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);
        $this->handler = new LoginHandler(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtTokenGenerator,
        );
    }

    public function testHandleTherapistLoginSuccess(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();

        $this->userRepository->method('findByEmail')->willReturn($therapist);
        $this->passwordHasher->method('verify')->willReturn(true);
        $this->jwtTokenGenerator->method('generate')->willReturn('jwt-token-123');

        $result = $this->handler->handleTherapistLogin('therapist@example.com', 'password');

        $this->assertSame('jwt-token-123', $result->token);
        $this->assertSame('therapist@example.com', $result->user->email);
        $this->assertSame('ROLE_THERAPIST', $result->user->role);
    }

    public function testHandlePatientLoginSuccess(): void
    {
        $patient = DomainTestHelper::createReconstitutedActivePatient();

        $this->userRepository->method('findByEmail')->willReturn($patient);
        $this->passwordHasher->method('verify')->willReturn(true);
        $this->jwtTokenGenerator->method('generate')->willReturn('jwt-token-456');

        $result = $this->handler->handlePatientLogin('patient@example.com', 'password');

        $this->assertSame('jwt-token-456', $result->token);
        $this->assertSame('ROLE_PATIENT', $result->user->role);
    }

    public function testHandleTherapistLoginUserNotFoundThrowsInvalidCredentials(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->handleTherapistLogin('unknown@example.com', 'password');
    }

    public function testHandleTherapistLoginWrongRoleThrowsInvalidCredentials(): void
    {
        $patient = DomainTestHelper::createReconstitutedActivePatient();
        $this->userRepository->method('findByEmail')->willReturn($patient);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->handleTherapistLogin('patient@example.com', 'password');
    }

    public function testHandlePatientLoginWrongRoleThrowsInvalidCredentials(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();
        $this->userRepository->method('findByEmail')->willReturn($therapist);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->handlePatientLogin('therapist@example.com', 'password');
    }

    public function testHandleTherapistLoginInactiveUserThrowsUserNotActive(): void
    {
        $inactivePatient = DomainTestHelper::createReconstitutedInactivePatient(
            email: 'inactive@example.com',
        );
        // Reconstitute as therapist but inactive
        $inactiveTherapist = \App\Domain\User\Entity\User::reconstitute(
            id: $inactivePatient->getId(),
            email: $inactivePatient->getEmail(),
            fullName: 'Inactive Therapist',
            role: \App\Domain\User\ValueObject\UserRole::THERAPIST,
            password: 'hashed',
            phone: null,
            address: null,
            isActive: false,
            createdAt: $inactivePatient->getCreatedAt(),
            activatedAt: null,
            updatedAt: $inactivePatient->getUpdatedAt(),
        );

        $this->userRepository->method('findByEmail')->willReturn($inactiveTherapist);

        $this->expectException(UserNotActiveException::class);
        $this->handler->handleTherapistLogin('inactive@example.com', 'password');
    }

    public function testHandleTherapistLoginWrongPasswordThrowsInvalidCredentials(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();
        $this->userRepository->method('findByEmail')->willReturn($therapist);
        $this->passwordHasher->method('verify')->willReturn(false);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->handleTherapistLogin('therapist@example.com', 'wrong-password');
    }
}
