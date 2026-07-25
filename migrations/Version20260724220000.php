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

final class Version20260724220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional value and currency columns to the milestones table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('gppro_milestones')->hasColumn('value')) {
            $this->addSql('ALTER TABLE gppro_milestones ADD value NUMERIC(18, 4) DEFAULT NULL');
        }

        if (!$schema->getTable('gppro_milestones')->hasColumn('currency')) {
            $this->addSql('ALTER TABLE gppro_milestones ADD currency VARCHAR(3) DEFAULT NULL');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('gppro_milestones')->hasColumn('value')) {
            $this->addSql('ALTER TABLE gppro_milestones DROP value');
        }

        if ($schema->getTable('gppro_milestones')->hasColumn('currency')) {
            $this->addSql('ALTER TABLE gppro_milestones DROP currency');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
