<?php

declare(strict_types=1);

namespace App\Application\User\DTO\Input;

use App\Application\Shared\DTO\PaginationInputDTO;

final readonly class ListPatientsInputDTO
{
    public function __construct(
        public PaginationInputDTO $pagination = new PaginationInputDTO(),
    ) {
    }
}
