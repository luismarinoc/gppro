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

final class Version20260814110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_confirmed_at and rejected_at to gppro_users for the self-registration admin-approval gate';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('gppro_users')->hasColumn('email_confirmed_at')) {
            $this->addSql('ALTER TABLE gppro_users ADD email_confirmed_at DATETIME DEFAULT NULL');
        }

        if (!$schema->getTable('gppro_users')->hasColumn('rejected_at')) {
            $this->addSql('ALTER TABLE gppro_users ADD rejected_at DATETIME DEFAULT NULL');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('gppro_users')->hasColumn('email_confirmed_at')) {
            $this->addSql('ALTER TABLE gppro_users DROP COLUMN email_confirmed_at');
        }

        if ($schema->getTable('gppro_users')->hasColumn('rejected_at')) {
            $this->addSql('ALTER TABLE gppro_users DROP COLUMN rejected_at');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
