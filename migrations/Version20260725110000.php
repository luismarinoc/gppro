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
final class Version20260725110000 extends AbstractMigration
{
    private const FK_NAME = 'FK_5465ADE82989F1FD';
    private const INDEX_NAME = 'IDX_GPPRO_MILESTONES_INVOICE';

    public function getDescription(): string
    {
        return 'Adds a nullable invoice_id FK (ON DELETE SET NULL) to gppro_milestones, with an explicitly named index so it never desynchronizes from Doctrine\'s hashed naming';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('gppro_milestones');

        if (!$table->hasColumn('invoice_id')) {
            $this->addSql('ALTER TABLE gppro_milestones ADD invoice_id INT DEFAULT NULL');
        }

        if (!$table->hasIndex(self::INDEX_NAME)) {
            $this->addSql('CREATE INDEX ' . self::INDEX_NAME . ' ON gppro_milestones (invoice_id)');
        }

        if (!$table->hasForeignKey(self::FK_NAME)) {
            $this->addSql(
                'ALTER TABLE gppro_milestones ADD CONSTRAINT ' . self::FK_NAME
                . ' FOREIGN KEY (invoice_id) REFERENCES gppro_invoices (id) ON DELETE SET NULL'
            );
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('gppro_milestones');

        if ($table->hasForeignKey(self::FK_NAME)) {
            $this->addSql('ALTER TABLE gppro_milestones DROP FOREIGN KEY ' . self::FK_NAME);
        }

        if ($table->hasIndex(self::INDEX_NAME)) {
            $this->addSql('DROP INDEX ' . self::INDEX_NAME . ' ON gppro_milestones');
        }

        if ($table->hasColumn('invoice_id')) {
            $this->addSql('ALTER TABLE gppro_milestones DROP invoice_id');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
