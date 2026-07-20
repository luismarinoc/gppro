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
final class Version20260720214501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate the internal auth value on gppro_users from the legacy "kimai" to "internal"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE gppro_users SET auth = 'internal' WHERE auth = 'kimai'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE gppro_users SET auth = 'kimai' WHERE auth = 'internal'");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
