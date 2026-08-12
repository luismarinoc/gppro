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

final class Version20260811230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a payment term (30/60/90 days) to quotations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations ADD payment_term_days INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations DROP payment_term_days');
    }
}
