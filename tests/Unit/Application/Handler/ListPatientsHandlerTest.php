<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Handler;

use App\Application\User\Handler\ListPatientsHandler;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListPatientsHandlerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private ListPatientsHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->handler = new ListPatientsHandler($this->userRepository);
    }

    public function testHandleReturnsMappedDTOs(): void
    {
        $patient1 = DomainTestHelper::createActivePatient(email: 'p1@example.com', fullName: 'Patient 1');
        $patient2 = DomainTestHelper::createActivePatient(email: 'p2@example.com', fullName: 'Patient 2');

        $this->userRepository
            ->method('findActivePatients')
            ->willReturn(new ArrayCollection([$patient1, $patient2]));

        $result = $this->handler->handle();

        $this->assertCount(2, $result);
        $this->assertSame('p1@example.com', $result->get(0)->email);
        $this->assertSame('p2@example.com', $result->get(1)->email);
    }

    public function testHandleEmptyListReturnsEmptyCollection(): void
    {
        $this->userRepository
            ->method('findActivePatients')
            ->willReturn(new ArrayCollection());

        $result = $this->handler->handle();

        $this->assertCount(0, $result);
    }
}
