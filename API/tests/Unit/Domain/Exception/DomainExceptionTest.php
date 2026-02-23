<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exception;

use App\Domain\Exception\DomainException;
use App\Domain\User\Exception\InvalidCredentialsException;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Exception\UserNotActiveException;
use App\Domain\User\Exception\UserNotFoundException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testInvalidCredentialsExceptionExtendsDomainException(): void
    {
        $exception = new InvalidCredentialsException();
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testInvalidTokenExpiredHasDistinctErrorCode(): void
    {
        $exception = InvalidTokenException::expired();
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testInvalidTokenAlreadyUsedHasDistinctErrorCode(): void
    {
        $exception = InvalidTokenException::alreadyUsed();
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testInvalidTokenNotFoundHasDistinctErrorCode(): void
    {
        $exception = InvalidTokenException::notFound();
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testInvalidTokenFactoryMethodsReturnDistinctCodes(): void
    {
        $expired = InvalidTokenException::expired();
        $used = InvalidTokenException::alreadyUsed();
        $notFound = InvalidTokenException::notFound();

        $codes = [
            $expired->getErrorCode(),
            $used->getErrorCode(),
            $notFound->getErrorCode(),
        ];

        $this->assertCount(3, array_unique($codes));
    }

    public function testUserAlreadyExistsExceptionIncludesEmailInMessage(): void
    {
        $exception = new UserAlreadyExistsException('test@example.com');
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertStringContainsString('test@example.com', $exception->getMessage());
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testUserNotActiveExceptionExtendsDomainException(): void
    {
        $exception = new UserNotActiveException();
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertNotEmpty($exception->getErrorCode());
    }

    public function testUserNotFoundExceptionIncludesIdentifierInMessage(): void
    {
        $exception = new UserNotFoundException('some-uuid');
        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertStringContainsString('some-uuid', $exception->getMessage());
        $this->assertNotEmpty($exception->getErrorCode());
    }
}
