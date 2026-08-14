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

final class Version20260814130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add login audit trail (gppro_login_attempts)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gppro_login_attempts (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, attempted_username VARCHAR(180) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, outcome VARCHAR(10) NOT NULL, failure_reason VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_GPPRO_LOGIN_ATTEMPTS_CREATED_AT (created_at), INDEX IDX_GPPRO_LOGIN_ATTEMPTS_USER (user_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_login_attempts ADD CONSTRAINT FK_GPPRO_LOGIN_ATTEMPTS_USER FOREIGN KEY (user_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // $schema->dropTable() emits the FK-drop itself (schema diff-based);
        // an explicit addSql('ALTER TABLE ... DROP FOREIGN KEY ...') here
        // would run redundantly and fail once the FK is already gone.
        $schema->dropTable('gppro_login_attempts');
    }
}
