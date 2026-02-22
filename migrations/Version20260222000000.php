<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment_verified column to appointments table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointments ADD COLUMN payment_verified BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointments DROP COLUMN payment_verified');
    }
}
