<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, invitation_tokens, and password_reset_tokens tables';
    }

    public function up(Schema $schema): void
    {
        // Users table
        $this->addSql('CREATE TABLE users (
            id UUID NOT NULL,
            email VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            password VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address_street VARCHAR(255) DEFAULT NULL,
            address_city VARCHAR(100) DEFAULT NULL,
            address_state VARCHAR(100) DEFAULT NULL,
            address_postal_code VARCHAR(20) DEFAULT NULL,
            address_country VARCHAR(100) DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            activated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_role ON users (role)');
        
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.activated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Invitation tokens table
        $this->addSql('CREATE TABLE invitation_tokens (
            id UUID NOT NULL,
            token VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            patient_name VARCHAR(255) NOT NULL,
            invited_by UUID NOT NULL,
            is_used BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_invitation_token ON invitation_tokens (token)');
        $this->addSql('CREATE INDEX idx_invitation_token ON invitation_tokens (token)');
        $this->addSql('CREATE INDEX idx_invitation_email ON invitation_tokens (email)');
        $this->addSql('CREATE INDEX idx_invitation_valid ON invitation_tokens (is_used, expires_at)');
        
        $this->addSql('COMMENT ON COLUMN invitation_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invitation_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invitation_tokens.used_at IS \'(DC2Type:datetime_immutable)\'');

        // Password reset tokens table
        $this->addSql('CREATE TABLE password_reset_tokens (
            id UUID NOT NULL,
            token VARCHAR(255) NOT NULL,
            user_id UUID NOT NULL,
            is_used BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_password_reset_token ON password_reset_tokens (token)');
        $this->addSql('CREATE INDEX idx_password_reset_token ON password_reset_tokens (token)');
        $this->addSql('CREATE INDEX idx_password_reset_user ON password_reset_tokens (user_id)');
        $this->addSql('CREATE INDEX idx_password_reset_valid ON password_reset_tokens (is_used, expires_at)');
        
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.used_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE invitation_tokens');
        $this->addSql('DROP TABLE users');
    }
}
