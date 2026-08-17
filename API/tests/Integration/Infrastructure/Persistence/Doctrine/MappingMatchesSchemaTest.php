<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine;

use App\Tests\Helper\IntegrationTestCase;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Guards the mapping attributes that only schema tooling can observe.
 *
 * Entities declare no Doctrine relation attributes here (see api-architecture.md), so the
 * update SQL always proposes dropping the hand-written FK constraints and indexes. That noise
 * is expected. These assertions target single objects instead of the whole diff.
 */
final class MappingMatchesSchemaTest extends IntegrationTestCase
{
    /** @var list<string> */
    private array $updateSql;

    protected function setUp(): void
    {
        parent::setUp();

        $schemaTool = new SchemaTool($this->entityManager);
        $this->updateSql = $schemaTool->getUpdateSchemaSql(
            $this->entityManager->getMetadataFactory()->getAllMetadata(),
        );
    }

    public function testScheduleExceptionIndexKeepsTheNameTheMigrationCreated(): void
    {
        // Version20260215000000 creates idx_exception_therapist_range. A mapping that names the
        // index differently makes Doctrine want to rename the real one.
        $renames = $this->statementsMatching('/RENAME TO idx_exception/i');

        self::assertSame([], $renames);
    }

    public function testDayOfWeekMatchesTheIntegerColumnTheMigrationCreated(): void
    {
        // Version20260215000000 creates day_of_week as INT.
        $typeChanges = $this->statementsMatching('/ALTER day_of_week TYPE/i');

        self::assertSame([], $typeChanges);
    }

    /** @return list<string> */
    private function statementsMatching(string $pattern): array
    {
        return array_values(array_filter(
            $this->updateSql,
            static fn (string $sql): bool => preg_match($pattern, $sql) === 1,
        ));
    }
}
