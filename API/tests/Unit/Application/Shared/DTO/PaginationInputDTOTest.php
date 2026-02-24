<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared\DTO;

use App\Application\Shared\DTO\PaginationInputDTO;
use PHPUnit\Framework\TestCase;

final class PaginationInputDTOTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $dto = new PaginationInputDTO();

        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->limit);
        $this->assertSame(0, $dto->offset);
    }

    public function testCustomValues(): void
    {
        $dto = new PaginationInputDTO(page: 3, limit: 10);

        $this->assertSame(3, $dto->page);
        $this->assertSame(10, $dto->limit);
        $this->assertSame(20, $dto->offset);
    }

    public function testPageMinimumIsOne(): void
    {
        $dto = new PaginationInputDTO(page: -1);

        $this->assertSame(1, $dto->page);
        $this->assertSame(0, $dto->offset);
    }

    public function testPageZeroClampedToOne(): void
    {
        $dto = new PaginationInputDTO(page: 0);

        $this->assertSame(1, $dto->page);
        $this->assertSame(0, $dto->offset);
    }

    public function testLimitCappedAt100(): void
    {
        $dto = new PaginationInputDTO(limit: 200);

        $this->assertSame(100, $dto->limit);
    }

    public function testLimitMinimumIsOne(): void
    {
        $dto = new PaginationInputDTO(limit: 0);

        $this->assertSame(1, $dto->limit);
    }

    public function testNullsUseDefaults(): void
    {
        $dto = new PaginationInputDTO(page: null, limit: null);

        $this->assertSame(1, $dto->page);
        $this->assertSame(20, $dto->limit);
        $this->assertSame(0, $dto->offset);
    }

    public function testOffsetCalculation(): void
    {
        $dto = new PaginationInputDTO(page: 5, limit: 25);

        $this->assertSame(100, $dto->offset);
    }
}
