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

final class Version20260807193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add secure quotation email delivery and response audit records';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gppro_quotation_emails (id INT AUTO_INCREMENT NOT NULL, quotation_id INT NOT NULL, recipient_email VARCHAR(255) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, sent_at DATETIME NOT NULL, response VARCHAR(8) DEFAULT NULL, responded_at DATETIME DEFAULT NULL, response_ip VARCHAR(45) DEFAULT NULL, response_user_agent VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_GPPRO_QUOTATION_EMAIL_TOKEN (token_hash), INDEX IDX_GPPRO_QUOTATION_EMAIL_QUOTATION (quotation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_quotation_emails ADD CONSTRAINT FK_GPPRO_QUOTATION_EMAIL_QUOTATION FOREIGN KEY (quotation_id) REFERENCES gppro_quotations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotation_emails DROP FOREIGN KEY FK_GPPRO_QUOTATION_EMAIL_QUOTATION');
        $schema->dropTable('gppro_quotation_emails');
    }
}
