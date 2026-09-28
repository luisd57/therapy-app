<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Doctrine\ORM\EntityManagerInterface;

/** Rolls back the transaction setUp() began, so no test sees another's rows. */
trait RollsBackTestTransaction
{
    protected EntityManagerInterface $entityManager;

    // @phpstan-ignore phpunit.callParent (the rule does not look inside finally, where the call is)
    protected function tearDown(): void
    {
        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $this->entityManager->close();
        } finally {
            parent::tearDown();
        }
    }
}
