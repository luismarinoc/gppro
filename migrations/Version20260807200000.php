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

final class Version20260807200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link quotations to their single invoice';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations ADD invoice_id INT DEFAULT NULL, ADD UNIQUE INDEX UNIQ_GPPRO_QUOTATION_INVOICE (invoice_id)');
        $this->addSql('ALTER TABLE gppro_quotations ADD CONSTRAINT FK_GPPRO_QUOTATION_INVOICE FOREIGN KEY (invoice_id) REFERENCES gppro_invoices (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations DROP FOREIGN KEY FK_GPPRO_QUOTATION_INVOICE');
        $this->addSql('ALTER TABLE gppro_quotations DROP INDEX UNIQ_GPPRO_QUOTATION_INVOICE');
        $this->addSql('ALTER TABLE gppro_quotations DROP invoice_id');
    }
}
