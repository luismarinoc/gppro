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

final class Version20260818180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional footer notes to quotations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations ADD notes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotations DROP notes');
    }
}
