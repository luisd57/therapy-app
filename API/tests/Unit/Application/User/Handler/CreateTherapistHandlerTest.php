<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\CreateTherapistInputDTO;
use App\Application\User\Handler\CreateTherapistHandler;
use App\Domain\User\Exception\TherapistAlreadyExistsException;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\Service\PasswordHasherInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateTherapistHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private ClockInterface&MockObject $clock;
    private CreateTherapistHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->handler = new CreateTherapistHandler($this->userRepository, $this->passwordHasher, $this->clock);
    }

    public function testHandleSuccessCreatesTherapistAndReturnsDTO(): void
    {
        $this->userRepository->method('findByRole')->willReturn(new ArrayCollection());
        $this->userRepository->method('existsByEmail')->willReturn(false);
        $this->passwordHasher->method('hash')->willReturn('hashed_pw');
        $this->userRepository->expects($this->once())->method('save');

        $input = new CreateTherapistInputDTO(
            email: 'dr@example.com',
            fullName: 'Dr. Test',
            password: 'securepass',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('dr@example.com', $result->email);
        $this->assertSame('Dr. Test', $result->fullName);
        $this->assertSame('ROLE_THERAPIST', $result->role);
        $this->assertTrue($result->isActive);
    }

    public function testHandleTherapistAlreadyExistsThrowsException(): void
    {
        $therapist = \App\Tests\Helper\DomainTestHelper::createTherapist();
        $this->userRepository->method('findByRole')->willReturn(new ArrayCollection([$therapist]));

        $input = new CreateTherapistInputDTO(
            email: 'dr@example.com',
            fullName: 'Dr. Second',
            password: 'securepass',
        );

        $this->expectException(TherapistAlreadyExistsException::class);
        $this->handler->__invoke($input);
    }

    public function testHandleDuplicateEmailThrowsUserAlreadyExistsException(): void
    {
        $this->userRepository->method('findByRole')->willReturn(new ArrayCollection());
        $this->userRepository->method('existsByEmail')->willReturn(true);

        $input = new CreateTherapistInputDTO(
            email: 'existing@example.com',
            fullName: 'Dr. Duplicate',
            password: 'securepass',
        );

        $this->expectException(UserAlreadyExistsException::class);
        $this->handler->__invoke($input);
    }

    public function testHandleSuccessHashesPassword(): void
    {
        $this->userRepository->method('findByRole')->willReturn(new ArrayCollection());
        $this->userRepository->method('existsByEmail')->willReturn(false);
        $this->passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with('securepass')
            ->willReturn('hashed_pw');

        $input = new CreateTherapistInputDTO(
            email: 'dr@example.com',
            fullName: 'Dr. Test',
            password: 'securepass',
        );

        $this->handler->__invoke($input);
    }
}
