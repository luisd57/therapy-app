<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\RevokeInvitationInputDTO;
use App\Application\User\Handler\RevokeInvitationHandler;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;

final class RevokeInvitationHandlerTest extends TestCase
{
    private InvitationTokenRepositoryInterface&MockObject $repository;
    private ClockInterface&MockObject $clock;
    private RevokeInvitationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new DateTimeImmutable());

        $this->handler = new RevokeInvitationHandler($this->repository, $this->clock);
    }

    public function testRevokesPendingInvitation(): void
    {
        $tokenId = TokenId::generate();
        $invitation = DomainTestHelper::createValidInvitation(id: $tokenId);

        $this->repository->method('findById')->willReturn($invitation);
        $this->repository->expects($this->once())->method('save')->with($invitation);

        $result = $this->handler->__invoke(
            new RevokeInvitationInputDTO(tokenId: $tokenId->getValue()),
        );

        $this->assertTrue($invitation->isRevoked());
        $this->assertSame('revoked', $result->status);
    }

    public function testThrowsWhenInvitationNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(InvitationNotFoundException::class);

        $this->handler->__invoke(
            new RevokeInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
        );
    }

    public function testThrowsWhenInvitationAlreadyUsed(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();
        $this->repository->method('findById')->willReturn($invitation);

        $this->expectException(\DomainException::class);

        $this->handler->__invoke(
            new RevokeInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
        );
    }

    public function testThrowsWhenInvitationAlreadyRevoked(): void
    {
        $invitation = DomainTestHelper::createRevokedInvitation();
        $this->repository->method('findById')->willReturn($invitation);

        $this->expectException(\DomainException::class);

        $this->handler->__invoke(
            new RevokeInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
        );
    }
}
