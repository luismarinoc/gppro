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

final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add invoice payment approval state machine and audit trail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_invoices ADD payment_approval_status VARCHAR(20) DEFAULT NULL, ADD payment_required_levels INT DEFAULT NULL, ADD payment_current_level INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE gppro_invoice_payment_approvals (id INT AUTO_INCREMENT NOT NULL, invoice_id INT NOT NULL, approved_by_id INT DEFAULT NULL, level INT NOT NULL, decision VARCHAR(20) NOT NULL, approved_at DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_GPPRO_INVOICE_PAYMENT_APPROVALS_APPROVED_BY (approved_by_id), UNIQUE INDEX UNIQ_GPPRO_INVOICE_PAYMENT_APPROVALS_INVOICE_LEVEL (invoice_id, level), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_invoice_payment_approvals ADD CONSTRAINT FK_GPPRO_INVOICE_PAYMENT_APPROVALS_INVOICE FOREIGN KEY (invoice_id) REFERENCES gppro_invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_invoice_payment_approvals ADD CONSTRAINT FK_GPPRO_INVOICE_PAYMENT_APPROVALS_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_invoice_payment_approvals DROP FOREIGN KEY FK_GPPRO_INVOICE_PAYMENT_APPROVALS_INVOICE');
        $this->addSql('ALTER TABLE gppro_invoice_payment_approvals DROP FOREIGN KEY FK_GPPRO_INVOICE_PAYMENT_APPROVALS_APPROVED_BY');
        $this->addSql('DROP TABLE gppro_invoice_payment_approvals');
        $this->addSql('ALTER TABLE gppro_invoices DROP payment_approval_status, DROP payment_required_levels, DROP payment_current_level');
    }
}
