<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class IntegrationTestCase extends KernelTestCase
{
    use FreezesClock;
    use RollsBackTestTransaction;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
    }

    /** The console application over the kernel setUp() booted. */
    protected static function consoleApplication(): Application
    {
        return new Application(self::$kernel ?? throw new \LogicException('setUp() boots the kernel.'));
    }
}
