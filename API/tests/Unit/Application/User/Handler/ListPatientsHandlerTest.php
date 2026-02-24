<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User\Handler;

use App\Application\Shared\DTO\PaginationInputDTO;
use App\Application\User\DTO\Input\ListPatientsInputDTO;
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
            ->method('findActivePatientsPaginated')
            ->with(0, 20)
            ->willReturn(new ArrayCollection([$patient1, $patient2]));

        $this->userRepository
            ->method('countActivePatients')
            ->willReturn(2);

        $result = $this->handler->__invoke(new ListPatientsInputDTO());

        $this->assertCount(2, $result->items);
        $this->assertSame('p1@example.com', $result->items->get(0)->email);
        $this->assertSame('p2@example.com', $result->items->get(1)->email);
        $this->assertSame(2, $result->total);
        $this->assertSame(1, $result->page);
    }

    public function testHandleEmptyListReturnsEmptyCollection(): void
    {
        $this->userRepository
            ->method('findActivePatientsPaginated')
            ->with(0, 20)
            ->willReturn(new ArrayCollection());

        $this->userRepository
            ->method('countActivePatients')
            ->willReturn(0);

        $result = $this->handler->__invoke(new ListPatientsInputDTO());

        $this->assertCount(0, $result->items);
        $this->assertSame(0, $result->total);
    }

    public function testHandleWithCustomPagination(): void
    {
        $patient = DomainTestHelper::createActivePatient(email: 'p1@example.com', fullName: 'Patient 1');

        $this->userRepository
            ->method('findActivePatientsPaginated')
            ->with(10, 5)
            ->willReturn(new ArrayCollection([$patient]));

        $this->userRepository
            ->method('countActivePatients')
            ->willReturn(12);

        $result = $this->handler->__invoke(new ListPatientsInputDTO(
            pagination: new PaginationInputDTO(page: 3, limit: 5),
        ));

        $this->assertCount(1, $result->items);
        $this->assertSame(3, $result->page);
        $this->assertSame(5, $result->limit);
        $this->assertSame(12, $result->total);
        $this->assertSame(3, $result->totalPages);
    }
}
