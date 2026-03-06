<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\UpdatePatientProfileInputDTO;
use App\Application\User\Handler\UpdatePatientProfileHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdatePatientProfileHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private ClockInterface&MockObject $clock;
    private UpdatePatientProfileHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable());
        $this->handler = new UpdatePatientProfileHandler($this->userRepository, $this->clock);
    }

    public function testHandleUpdatePhoneOnly(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save');

        $input = new UpdatePatientProfileInputDTO(
            userId: $user->getId()->getValue(),
            phone: '+1234567890',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('+1234567890', $result->phone);
        $this->assertNull($result->address);
    }

    public function testHandleUpdateAddressOnly(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->expects($this->once())->method('save');

        $input = new UpdatePatientProfileInputDTO(
            userId: $user->getId()->getValue(),
            street: '123 Main St',
            city: 'Springfield',
            country: 'USA',
            postalCode: '62701',
            state: 'IL',
        );

        $result = $this->handler->__invoke($input);

        $this->assertNull($result->phone);
        $this->assertNotNull($result->address);
        $this->assertSame('123 Main St', $result->address->street);
    }

    public function testHandleUpdateBothPhoneAndAddress(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $this->userRepository->method('findById')->willReturn($user);

        $input = new UpdatePatientProfileInputDTO(
            userId: $user->getId()->getValue(),
            phone: '+9876543210',
            street: '456 Oak Ave',
            city: 'Portland',
            country: 'USA',
        );

        $result = $this->handler->__invoke($input);

        $this->assertSame('+9876543210', $result->phone);
        $this->assertNotNull($result->address);
        $this->assertSame('456 Oak Ave', $result->address->street);
    }

    public function testHandleUserNotFoundThrowsUserNotFoundException(): void
    {
        $this->userRepository->method('findById')->willReturn(null);

        $input = new UpdatePatientProfileInputDTO(
            userId: '019510ab-1234-7000-8000-000000000001',
            phone: '+1234567890',
        );

        $this->expectException(UserNotFoundException::class);
        $this->handler->__invoke($input);
    }

    public function testHandlePartialAddressDoesNotCreateAddress(): void
    {
        $user = DomainTestHelper::createActivePatient();
        $this->userRepository->method('findById')->willReturn($user);

        $input = new UpdatePatientProfileInputDTO(
            userId: $user->getId()->getValue(),
            street: '123 Main St',
            city: 'Springfield',
            // country is missing, so address should NOT be created
        );

        $result = $this->handler->__invoke($input);

        $this->assertNull($result->address);
    }
}
