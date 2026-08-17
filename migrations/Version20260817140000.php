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

final class Version20260817140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a validated currency (CLP/USD/CLF) to expenses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE gppro_expenses ADD currency VARCHAR(3) NOT NULL DEFAULT 'CLP'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expenses DROP currency');
    }
}
