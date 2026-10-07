<?php

declare(strict_types=1);

namespace App\Tests\Unit\PHPStan\Rule\Fixture;

function waits(): void
{
    sleep(1);
    usleep(1000);
    \sleep(1);
    time_nanosleep(0, 1);
}
