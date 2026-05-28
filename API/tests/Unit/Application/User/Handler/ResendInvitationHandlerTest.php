<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\DTO\Input\ResendInvitationInputDTO;
use App\Application\User\Handler\ResendInvitationHandler;
use App\Domain\User\Entity\InvitationToken;
use App\Domain\User\Exception\InvalidTokenException;
use App\Domain\User\Exception\InvitationNotFoundException;
use App\Domain\User\Id\TokenId;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Domain\User\Service\EmailSenderInterface;
use App\Domain\User\Service\TokenGeneratorInterface;
use App\Tests\Helper\DomainTestHelper;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final class ResendInvitationHandlerTest extends TestCase
{
    private const FRONTEND_URL = 'http://localhost:4200';
    private const INVITATION_TTL = 86400;
    private const FRESH_TOKEN = 'fresh-raw-token-abc';

    private InvitationTokenRepositoryInterface&MockObject $repository;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private EmailSenderInterface&MockObject $emailSender;
    private ClockInterface&MockObject $clock;
    private LoggerInterface&MockObject $logger;
    private ResendInvitationHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $this->emailSender = $this->createMock(EmailSenderInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(new DateTimeImmutable());
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ResendInvitationHandler(
            $this->repository,
            $this->tokenGenerator,
            $this->emailSender,
            self::FRONTEND_URL,
            self::INVITATION_TTL,
            $this->clock,
            $this->logger,
        );
    }

    public function testRevokesOriginalIssuesFreshTokenAndSendsEmail(): void
    {
        $originalId = TokenId::generate();
        $original = DomainTestHelper::createValidInvitation(
            id: $originalId,
            email: 'patient@example.com',
            patientName: 'Jane Doe',
        );

        $this->repository->method('findById')->willReturn($original);
        $this->tokenGenerator->method('generate')->willReturn(self::FRESH_TOKEN);

        $savedEntities = [];
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (InvitationToken $invitation) use (&$savedEntities): void {
                $savedEntities[] = $invitation;
            });

        $this->emailSender->expects($this->once())
            ->method('sendInvitation')
            ->with(
                $this->callback(fn ($email) => $email->getValue() === 'patient@example.com'),
                'Jane Doe',
                self::FRONTEND_URL . '/register?token=' . self::FRESH_TOKEN,
            );

        $result = $this->handler->__invoke(
            new ResendInvitationInputDTO(tokenId: $originalId->getValue()),
        );

        $this->assertCount(2, $savedEntities);
        $this->assertTrue($savedEntities[0]->isRevoked(), 'Original invitation should be revoked');
        $this->assertFalse($savedEntities[1]->isRevoked(), 'New invitation should not be revoked');
        $this->assertFalse($savedEntities[1]->isUsed());
        $this->assertSame('pending', $result->status);
        $this->assertSame('patient@example.com', $result->email);
        $this->assertSame('Jane Doe', $result->patientName);
    }

    public function testThrowsWhenInvitationNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(InvitationNotFoundException::class);

        $this->handler->__invoke(
            new ResendInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
        );
    }

    public function testThrowsWhenInvitationAlreadyUsed(): void
    {
        $invitation = DomainTestHelper::createUsedInvitation();
        $this->repository->method('findById')->willReturn($invitation);
        $this->repository->expects($this->never())->method('save');

        try {
            $this->handler->__invoke(
                new ResendInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
            );
            $this->fail('Expected InvalidTokenException');
        } catch (InvalidTokenException $e) {
            $this->assertSame('TOKEN_ALREADY_USED', $e->getErrorCode());
        }
    }

    public function testThrowsWhenInvitationAlreadyRevoked(): void
    {
        $invitation = DomainTestHelper::createRevokedInvitation();
        $this->repository->method('findById')->willReturn($invitation);
        $this->repository->expects($this->never())->method('save');

        try {
            $this->handler->__invoke(
                new ResendInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
            );
            $this->fail('Expected InvalidTokenException');
        } catch (InvalidTokenException $e) {
            $this->assertSame('TOKEN_REVOKED', $e->getErrorCode());
        }
    }

    public function testSwallowsEmailSendFailureButStillReturnsNewInvitation(): void
    {
        $original = DomainTestHelper::createValidInvitation();
        $this->repository->method('findById')->willReturn($original);
        $this->tokenGenerator->method('generate')->willReturn(self::FRESH_TOKEN);

        $this->emailSender->method('sendInvitation')
            ->willThrowException(new \RuntimeException('SMTP down'));

        $this->logger->expects($this->once())->method('error');

        $result = $this->handler->__invoke(
            new ResendInvitationInputDTO(tokenId: TokenId::generate()->getValue()),
        );

        $this->assertSame('pending', $result->status);
    }
}
