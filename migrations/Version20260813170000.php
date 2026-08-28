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

final class Version20260813170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gppro_activities_comments (id INT AUTO_INCREMENT NOT NULL, activity_id INT NOT NULL, created_by_id INT NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, pinned TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_GPPRO_ACTIVITIES_COMMENTS_ACTIVITY (activity_id), INDEX IDX_GPPRO_ACTIVITIES_COMMENTS_CREATED_BY (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_activities_comments ADD CONSTRAINT FK_GPPRO_ACTIVITIES_COMMENTS_ACTIVITY FOREIGN KEY (activity_id) REFERENCES gppro_activities (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_activities_comments ADD CONSTRAINT FK_GPPRO_ACTIVITIES_COMMENTS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES gppro_users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('gppro_activities_comments');
    }
}
