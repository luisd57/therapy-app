<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine;

use App\Tests\Helper\IntegrationTestCase;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Guards the mapping attributes that only schema tooling can observe.
 * Asserts per object, not on the whole diff: see ORM Pragmatism in api-architecture.md.
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
        // Version20260215000000 creates idx_exception_therapist_range. Matching the object rather
        // than the RENAME form so a fresh DB or a different comparator output still fails here.
        $touchingTheIndex = $this->statementsMatching('/idx_exception/i');

        self::assertSame([], $touchingTheIndex);
    }

    public function testDayOfWeekMatchesTheIntegerColumnTheMigrationCreated(): void
    {
        // Version20260215000000 creates day_of_week as INT.
        $touchingTheColumn = $this->statementsMatching('/day_of_week/i');

        self::assertSame([], $touchingTheColumn);
    }

    public function testExceptionReasonMatchesTheNonNullColumnTheMigrationCreated(): void
    {
        // Version20260215000000 creates reason as NOT NULL. Matching both directions, so a mapping
        // that turns nullable fails too; the expected DROP DEFAULT line is what keeps this narrow.
        $touchingNullability = $this->statementsMatching('/reason (SET|DROP) NOT NULL/i');

        self::assertSame([], $touchingNullability);
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
