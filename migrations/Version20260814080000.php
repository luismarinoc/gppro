<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260814080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds nullable approved_by_id (FK, SET NULL) and approved_at columns to gppro_timesheet for team-lead single-step Timesheet approval';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_timesheet ADD approved_by_id INT DEFAULT NULL, ADD approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_GPPRO_TIMESHEET_APPROVED_BY ON gppro_timesheet (approved_by_id)');
        $this->addSql('ALTER TABLE gppro_timesheet ADD CONSTRAINT FK_GPPRO_TIMESHEET_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_timesheet DROP FOREIGN KEY FK_GPPRO_TIMESHEET_APPROVED_BY');
        $this->addSql('DROP INDEX IDX_GPPRO_TIMESHEET_APPROVED_BY ON gppro_timesheet');
        $this->addSql('ALTER TABLE gppro_timesheet DROP approved_by_id, DROP approved_at');
    }
}
