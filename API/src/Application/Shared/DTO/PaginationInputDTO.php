<?php

declare(strict_types=1);

namespace App\Application\Shared\DTO;

final readonly class PaginationInputDTO
{
    private const int DEFAULT_PAGE = 1;
    private const int DEFAULT_LIMIT = 20;
    private const int MAX_LIMIT = 100;

    public int $page;
    public int $limit;
    public int $offset;

    public function __construct(
        ?int $page = null,
        ?int $limit = null,
    ) {
        $this->page = max(1, $page ?? self::DEFAULT_PAGE);
        $this->limit = min(self::MAX_LIMIT, max(1, $limit ?? self::DEFAULT_LIMIT));
        $this->offset = ($this->page - 1) * $this->limit;
    }
}
