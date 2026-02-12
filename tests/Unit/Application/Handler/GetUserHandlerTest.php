<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\Handler\GetUserHandler;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetUserHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GetUserHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new GetUserHandler($this->userRepository);
    }

    public function testHandleUserFoundReturnsUserDTO(): void
    {
        $user = DomainTestHelper::createTherapist();
        $userId = $user->getId()->getValue();

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        $result = $this->handler->handle($userId);

        $this->assertSame($userId, $result->id);
        $this->assertSame('therapist@example.com', $result->email);
    }

    public function testHandleUserNotFoundThrowsUserNotFoundException(): void
    {
        $this->userRepository
            ->method('findById')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->handler->handle('019510ab-1234-7000-8000-000000000001');
    }
}
