<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\User\Handler\ListInvitationsHandler;
use App\Domain\User\Repository\InvitationTokenRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListInvitationsHandlerTest extends TestCase
{
    private InvitationTokenRepositoryInterface&MockObject $invitationRepository;
    private ListInvitationsHandler $handler;

    protected function setUp(): void
    {
        $this->invitationRepository = $this->createMock(InvitationTokenRepositoryInterface::class);
        $this->handler = new ListInvitationsHandler($this->invitationRepository);
    }

    public function testHandleReturnsMappedDTOs(): void
    {
        $inv1 = DomainTestHelper::createValidInvitation(token: 'tok1', email: 'p1@example.com', patientName: 'Patient 1');
        $inv2 = DomainTestHelper::createValidInvitation(token: 'tok2', email: 'p2@example.com', patientName: 'Patient 2');

        $this->invitationRepository
            ->method('findPendingInvitations')
            ->willReturn(new ArrayCollection([$inv1, $inv2]));

        $result = $this->handler->handle();

        $this->assertCount(2, $result);
        $this->assertSame('p1@example.com', $result->get(0)->email);
        $this->assertSame('p2@example.com', $result->get(1)->email);
        $this->assertSame('pending', $result->get(0)->status);
    }

    public function testHandleEmptyListReturnsEmptyCollection(): void
    {
        $this->invitationRepository
            ->method('findPendingInvitations')
            ->willReturn(new ArrayCollection());

        $result = $this->handler->handle();

        $this->assertCount(0, $result);
    }
}
