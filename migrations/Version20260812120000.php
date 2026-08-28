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

final class Version20260812120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the unused unit column from the quotation catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotation_catalog_items DROP unit');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotation_catalog_items ADD unit VARCHAR(50) NOT NULL DEFAULT \'\'');
    }
}
