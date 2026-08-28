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

final class Version20260828130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds explicit Timesheet approval states and immutable decision history'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_timesheet ADD approval_status VARCHAR(30) NOT NULL DEFAULT \'draft\', ADD approval_attempt INT NOT NULL DEFAULT 1');
        $this->addSql('UPDATE gppro_timesheet SET approval_status = \'approved\' WHERE approved_at IS NOT NULL');
        $this->addSql('CREATE TABLE gppro_timesheet_approvals (id INT AUTO_INCREMENT NOT NULL, timesheet_id INT NOT NULL, approval_attempt INT NOT NULL DEFAULT 1, decision VARCHAR(20) NOT NULL, decided_by_id INT DEFAULT NULL, decided_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', note LONGTEXT DEFAULT NULL, INDEX IDX_GPPRO_TS_APPROVAL_TS_ATTEMPT (timesheet_id, approval_attempt), INDEX IDX_GPPRO_TS_APPROVAL_DECIDED_BY (decided_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_timesheet_approvals ADD CONSTRAINT FK_GPPRO_TS_APPROVAL_TS FOREIGN KEY (timesheet_id) REFERENCES gppro_timesheet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_timesheet_approvals ADD CONSTRAINT FK_GPPRO_TS_APPROVAL_USER FOREIGN KEY (decided_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('gppro_timesheet_approvals');
        $this->addSql('ALTER TABLE gppro_timesheet DROP approval_status, DROP approval_attempt');
    }
}
