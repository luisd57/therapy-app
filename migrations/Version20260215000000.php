<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create therapist_schedules, schedule_exceptions, appointments, and slot_locks tables';
    }

    public function up(Schema $schema): void
    {
        // Therapist schedules table
        $this->addSql('CREATE TABLE therapist_schedules (
            id UUID NOT NULL,
            therapist_id UUID NOT NULL,
            day_of_week INT NOT NULL,
            start_time VARCHAR(5) NOT NULL,
            end_time VARCHAR(5) NOT NULL,
            supports_online BOOLEAN NOT NULL DEFAULT TRUE,
            supports_in_person BOOLEAN NOT NULL DEFAULT TRUE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_schedule_therapist_day ON therapist_schedules (therapist_id, day_of_week)');

        $this->addSql('COMMENT ON COLUMN therapist_schedules.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN therapist_schedules.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Schedule exceptions table
        $this->addSql('CREATE TABLE schedule_exceptions (
            id UUID NOT NULL,
            therapist_id UUID NOT NULL,
            start_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            end_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            reason VARCHAR(500) DEFAULT \'\',
            is_all_day BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_exception_therapist_range ON schedule_exceptions (therapist_id, start_date_time, end_date_time)');

        $this->addSql('COMMENT ON COLUMN schedule_exceptions.start_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN schedule_exceptions.end_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN schedule_exceptions.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Appointments table
        $this->addSql('CREATE TABLE appointments (
            id UUID NOT NULL,
            start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            end_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            modality VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            city VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL,
            patient_id UUID DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_appointment_status ON appointments (status)');
        $this->addSql('CREATE INDEX idx_appointment_time_range ON appointments (start_time, end_time)');
        $this->addSql('CREATE INDEX idx_appointment_blocking ON appointments (status, start_time, end_time)');

        $this->addSql('COMMENT ON COLUMN appointments.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN appointments.end_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN appointments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN appointments.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Slot locks table
        $this->addSql('CREATE TABLE slot_locks (
            id UUID NOT NULL,
            start_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            end_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            modality VARCHAR(20) NOT NULL,
            lock_token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_slot_lock_token ON slot_locks (lock_token)');
        $this->addSql('CREATE INDEX idx_slot_lock_time_expires ON slot_locks (start_time, end_time, expires_at)');

        $this->addSql('COMMENT ON COLUMN slot_locks.start_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN slot_locks.end_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN slot_locks.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN slot_locks.expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE slot_locks');
        $this->addSql('DROP TABLE appointments');
        $this->addSql('DROP TABLE schedule_exceptions');
        $this->addSql('DROP TABLE therapist_schedules');
    }
}
