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
final class Version20260726222250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a nullable logo column to gppro_invoice_templates, storing an inline "data:" URI so PDF rendering never needs filesystem access to an upload directory.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('gppro_invoice_templates');

        if (!$table->hasColumn('logo')) {
            $this->addSql('ALTER TABLE gppro_invoice_templates ADD logo LONGTEXT DEFAULT NULL');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('gppro_invoice_templates');

        if ($table->hasColumn('logo')) {
            $this->addSql('ALTER TABLE gppro_invoice_templates DROP logo');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
