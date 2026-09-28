<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\Handler\ListInvitationsHandler;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\UsesUtcInstants;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Clock\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListInvitationsHandlerTest extends TestCase
{
    use UsesUtcInstants;

    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private ClockInterface&MockObject $clock;
    private ListInvitationsHandler $handler;

    protected function setUp(): void
    {
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn(self::utc('2026-06-15 12:00:00'));
        $this->handler = new ListInvitationsHandler($this->invitationRepository, $this->clock);
    }

    public function testHandleReturnsMappedDTOs(): void
    {
        // Issued an hour before the pinned now with a one-day TTL, so the status turns on the clock.
        $issuedAt = self::utc('2026-06-15 11:00:00');
        $inv1 = DomainTestHelper::createValidInvitation(token: 'tok1', email: 'p1@example.com', patientName: 'Patient 1', now: $issuedAt);
        $inv2 = DomainTestHelper::createValidInvitation(token: 'tok2', email: 'p2@example.com', patientName: 'Patient 2', now: $issuedAt);

        $this->invitationRepository
            ->method('findAll')
            ->willReturn(new ArrayCollection([$inv1, $inv2]));

        $result = $this->handler->__invoke();

        $this->assertCount(2, $result);
        $this->assertSame('p1@example.com', $result->get(0)?->email);
        $this->assertSame('p2@example.com', $result->get(1)?->email);
        $this->assertSame('pending', $result->get(0)->status);
    }

    public function testHandleEmptyListReturnsEmptyCollection(): void
    {
        $this->invitationRepository
            ->method('findAll')
            ->willReturn(new ArrayCollection());

        $result = $this->handler->__invoke();

        $this->assertCount(0, $result);
    }
}
