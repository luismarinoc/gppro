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

/**
 * @version 2.x
 */
final class Version20260728150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_type to gppro_users, classifying a user as functional, technical or guest';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('gppro_users')->hasColumn('user_type')) {
            $this->addSql('ALTER TABLE gppro_users ADD user_type VARCHAR(20) DEFAULT NULL');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('gppro_users')->hasColumn('user_type')) {
            $this->addSql('ALTER TABLE gppro_users DROP COLUMN user_type');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
