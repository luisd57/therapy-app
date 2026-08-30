<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hand-written on purpose - do NOT regenerate with doctrine:migrations:diff.
 *
 * True when this was written: entities declared no ORM relations, so Doctrine could not
 * see the hand-written FOREIGN KEY constraints or the hand-named indexes and would have
 * proposed dropping them alongside these type changes. ADR-0007 reversed that, and from
 * Version20260830114939 onward migrations are generated. This one stays as it is.
 */
final class Version20260810120000 extends AbstractMigration
{
    /**
     * Every application timestamp column. doctrine_migration_versions.executed_at
     * belongs to Doctrine and is deliberately left alone.
     *
     * @var array<string, list<string>>
     */
    private const array COLUMNS = [
        'appointments' => ['start_time', 'end_time', 'created_at', 'updated_at'],
        'invitation_tokens' => ['created_at', 'expires_at', 'used_at', 'revoked_at'],
        'password_reset_tokens' => ['created_at', 'expires_at', 'used_at'],
        'schedule_exceptions' => ['start_date_time', 'end_date_time', 'created_at'],
        'slot_locks' => ['start_time', 'end_time', 'created_at', 'expires_at'],
        // therapist_schedules.start_time/end_time are VARCHAR(5) wall-clock rules,
        // not instants - they must NOT be converted.
        'therapist_schedules' => ['created_at', 'updated_at'],
        'users' => ['created_at', 'activated_at', 'updated_at'],
    ];

    /**
     * The zone the existing naive values were written in: PHP's old default was
     * America/Caracas. Use the IANA name, never a fixed '-04:00' - Venezuela ran
     * -04:30 from 2007 to 2016, and only the named zone gets older rows right.
     */
    private const string LEGACY_TIMEZONE = 'America/Caracas';

    public function getDescription(): string
    {
        return 'Convert every instant column to TIMESTAMP WITH TIME ZONE (interpreting stored '
            . 'naive values as America/Caracas) and add requester/user timezone columns.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->addSql($this->alterColumns($table, $columns, 'WITH TIME ZONE'));

            foreach ($columns as $column) {
                // Stale DBAL type hint; the column is no longer datetime_immutable.
                $this->addSql(sprintf('COMMENT ON COLUMN %s.%s IS NULL', $table, $column));
            }
        }

        // Nullable with no backfill: the zone of an existing requester is genuinely
        // unknown, and defaulting to the practice zone would fabricate data. Readers
        // fall back to the practice timezone when these are null.
        $this->addSql('ALTER TABLE appointments ADD requester_timezone VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD timezone VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP timezone');
        $this->addSql('ALTER TABLE appointments DROP requester_timezone');

        foreach (self::COLUMNS as $table => $columns) {
            $this->addSql($this->alterColumns($table, $columns, 'WITHOUT TIME ZONE'));
        }
    }

    /**
     * AT TIME ZONE is self-inverse here: against a timestamptz it renders the
     * naive local reading, and against a naive timestamp it interprets one.
     *
     * @param list<string> $columns
     */
    private function alterColumns(string $table, array $columns, string $tzClause): string
    {
        $clauses = array_map(
            fn (string $column): string => sprintf(
                'ALTER COLUMN %s TYPE TIMESTAMP(0) %s USING %s AT TIME ZONE %s',
                $column,
                $tzClause,
                $column,
                $this->connection->quote(self::LEGACY_TIMEZONE),
            ),
            $columns,
        );

        return sprintf('ALTER TABLE %s %s', $table, implode(', ', $clauses));
    }
}
