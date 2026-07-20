<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * @version 2.x
 */
final class Version20260718033601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add milestones (project-scoped) and link activities to a milestone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE kimai2_milestones (
                  id INT AUTO_INCREMENT NOT NULL,
                  project_id INT NOT NULL,
                  name VARCHAR(150) NOT NULL,
                  comment LONGTEXT DEFAULT NULL,
                  due_date DATE DEFAULT NULL,
                  created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                  INDEX IDX_2517D07D166D1F9C (project_id),
                  PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE kimai2_milestones
                ADD CONSTRAINT FK_2517D07D166D1F9C FOREIGN KEY (project_id) REFERENCES kimai2_projects (id) ON DELETE CASCADE
            SQL);
        $this->addSql('ALTER TABLE kimai2_activities ADD milestone_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
                ALTER TABLE kimai2_activities
                ADD CONSTRAINT FK_8811FE1C4B3E2EDA FOREIGN KEY (milestone_id) REFERENCES kimai2_milestones (id) ON DELETE SET NULL
            SQL);
        $this->addSql('CREATE INDEX IDX_8811FE1C4B3E2EDA ON kimai2_activities (milestone_id)');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('kimai2_activities')->dropColumn('milestone_id');
        $schema->dropTable('kimai2_milestones');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
