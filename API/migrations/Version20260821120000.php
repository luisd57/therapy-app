<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hash the invitation and password reset tokens that were stored in plaintext, so the rows match what hashed_string now writes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE invitation_tokens SET token = encode(sha256(token::bytea), 'hex')");
        $this->addSql("UPDATE password_reset_tokens SET token = encode(sha256(token::bytea), 'hex')");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Tokens were hashed in place; the raw values are gone.');
    }
}
