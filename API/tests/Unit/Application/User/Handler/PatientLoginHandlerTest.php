<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\PatientLoginInputDTO;
use App\Domain\User\Service\JwtTokenGeneratorInterface;
use App\Application\User\Handler\PatientLoginHandler;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PatientLoginHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private JwtTokenGeneratorInterface&MockObject $jwtTokenGenerator;
    private PatientLoginHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->jwtTokenGenerator = $this->createMock(JwtTokenGeneratorInterface::class);
        $this->handler = new PatientLoginHandler(
            $this->userRepository,
            $this->passwordHasher,
            $this->jwtTokenGenerator,
        );
    }

    public function testLoginSuccess(): void
    {
        $patient = DomainTestHelper::createReconstitutedActivePatient();

        $this->userRepository->method('findByEmail')->willReturn($patient);
        $this->passwordHasher->method('verify')->willReturn(true);
        $this->jwtTokenGenerator->method('generate')->willReturn('jwt-token-456');

        $result = $this->handler->__invoke(new PatientLoginInputDTO('patient@example.com', 'password'));

        $this->assertSame('jwt-token-456', $result->token);
        $this->assertSame('ROLE_PATIENT', $result->user->role);
    }

    public function testWrongRoleThrowsInvalidCredentials(): void
    {
        $therapist = DomainTestHelper::createReconstitutedTherapist();
        $this->userRepository->method('findByEmail')->willReturn($therapist);

        $this->expectException(InvalidCredentialsException::class);
        $this->handler->__invoke(new PatientLoginInputDTO('therapist@example.com', 'password'));
    }
}
