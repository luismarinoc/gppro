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

final class Version20260814100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice payment approval levels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gppro_invoice_payment_approval_levels (id INT AUTO_INCREMENT NOT NULL, level INT NOT NULL, min_amount DOUBLE PRECISION NOT NULL, required_role VARCHAR(50) NOT NULL, approver_user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_GPPRO_INVOICE_PAYMENT_APPROVAL_LEVELS_LEVEL (level), INDEX IDX_GPPRO_INVOICE_PAYMENT_APPROVAL_LEVELS_APPROVER_USER (approver_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_invoice_payment_approval_levels ADD CONSTRAINT FK_GPPRO_INVOICE_PAYMENT_APPROVAL_LEVELS_APPROVER_USER FOREIGN KEY (approver_user_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
        $this->addSql('INSERT INTO gppro_invoice_payment_approval_levels (level, min_amount, required_role) VALUES (1, 0, \'ROLE_TEAMLEAD\')');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('gppro_invoice_payment_approval_levels');
    }
}
