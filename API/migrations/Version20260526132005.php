<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526132005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_revoked and revoked_at columns to invitation_tokens for therapist-initiated revoke and resend flows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_tokens ADD is_revoked BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE invitation_tokens ADD revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_tokens DROP is_revoked');
        $this->addSql('ALTER TABLE invitation_tokens DROP revoked_at');
    }
}
