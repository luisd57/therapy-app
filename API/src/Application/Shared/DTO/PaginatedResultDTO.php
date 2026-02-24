<?php

declare(strict_types=1);

namespace App\Application\Shared\DTO;

use Doctrine\Common\Collections\ArrayCollection;

final readonly class PaginatedResultDTO
{
    public int $totalPages;

    /**
     * @param ArrayCollection<int, mixed> $items
     */
    public function __construct(
        public ArrayCollection $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {
        $this->totalPages = $this->limit > 0 ? (int) ceil($this->total / $this->limit) : 0;
    }

    /**
     * @return array<string, int>
     */
    public function toMeta(): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'total' => $this->total,
            'total_pages' => $this->totalPages,
        ];
    }
}
