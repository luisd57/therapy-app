<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add foreign key constraints with cascade delete to all tables referencing users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_tokens ADD CONSTRAINT fk_invitation_tokens_invited_by FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_password_reset_tokens_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE therapist_schedules ADD CONSTRAINT fk_therapist_schedules_therapist_id FOREIGN KEY (therapist_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE schedule_exceptions ADD CONSTRAINT fk_schedule_exceptions_therapist_id FOREIGN KEY (therapist_id) REFERENCES users(id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointments ADD CONSTRAINT fk_appointments_patient_id FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_tokens DROP CONSTRAINT fk_invitation_tokens_invited_by');
        $this->addSql('ALTER TABLE password_reset_tokens DROP CONSTRAINT fk_password_reset_tokens_user_id');
        $this->addSql('ALTER TABLE therapist_schedules DROP CONSTRAINT fk_therapist_schedules_therapist_id');
        $this->addSql('ALTER TABLE schedule_exceptions DROP CONSTRAINT fk_schedule_exceptions_therapist_id');
        $this->addSql('ALTER TABLE appointments DROP CONSTRAINT fk_appointments_patient_id');
    }
}
