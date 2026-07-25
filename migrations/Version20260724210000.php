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
 * @version 2.63.0
 */
final class Version20260724210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the gppro_fx_rates table for the CL FX rate daily sync feature, with an explicitly named unique index on (date, indicator) so it never desynchronizes from Doctrine\'s hashed naming';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('gppro_fx_rates');

        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('date', 'date_immutable', ['notnull' => true]);
        $table->addColumn('indicator', 'string', ['notnull' => true, 'length' => 20]);
        $table->addColumn('rate_value', 'decimal', ['notnull' => true, 'precision' => 15, 'scale' => 6]);
        $table->addColumn('modified_at', 'datetime_immutable', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['date', 'indicator'], 'UNIQ_GPPRO_FX_RATES_DATE_INDICATOR');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('gppro_fx_rates')) {
            $schema->dropTable('gppro_fx_rates');
        }
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
