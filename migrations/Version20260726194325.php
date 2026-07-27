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
final class Version20260726194325 extends AbstractMigration
{
    private const TABLE_NAME = 'gppro_activities_board_state';
    private const ACTIVITY_FK_NAME = 'FK_4C7B4B7381C06096';
    private const USER_FK_NAME = 'FK_4C7B4B7389EEAF91';

    public function getDescription(): string
    {
        return 'Create the gppro_activities_board_state table for the Kanban activities board feature (status/priority/due date/assignee), unidirectionally linked to gppro_activities - Activity itself gains no columns or relations';
    }

    public function up(Schema $schema): void
    {
        $tableExists = $schema->hasTable(self::TABLE_NAME);

        if (!$tableExists) {
            $this->addSql(<<<'SQL'
                CREATE TABLE gppro_activities_board_state (
                  id INT AUTO_INCREMENT NOT NULL,
                  activity_id INT NOT NULL,
                  assigned_to INT DEFAULT NULL,
                  status VARCHAR(20) NOT NULL,
                  priority VARCHAR(20) DEFAULT NULL,
                  due_date DATE DEFAULT NULL,
                  INDEX IDX_GPPRO_ACTIVITIES_BOARD_USER (assigned_to),
                  UNIQUE INDEX UNIQ_GPPRO_ACTIVITIES_BOARD_ACTIVITY (activity_id),
                  PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
        }

        // Short-circuits before getTable() when the table was just created
        // above (addSql() does not mutate $schema, so hasTable() still
        // reports the pre-migration state - a brand-new table can never
        // already have these constraints).
        if (!$tableExists || !$schema->getTable(self::TABLE_NAME)->hasForeignKey(self::ACTIVITY_FK_NAME)) {
            $this->addSql(
                'ALTER TABLE ' . self::TABLE_NAME . ' ADD CONSTRAINT ' . self::ACTIVITY_FK_NAME
                . ' FOREIGN KEY (activity_id) REFERENCES gppro_activities (id) ON DELETE CASCADE'
            );
        }

        if (!$tableExists || !$schema->getTable(self::TABLE_NAME)->hasForeignKey(self::USER_FK_NAME)) {
            $this->addSql(
                'ALTER TABLE ' . self::TABLE_NAME . ' ADD CONSTRAINT ' . self::USER_FK_NAME
                . ' FOREIGN KEY (assigned_to) REFERENCES gppro_users (id) ON DELETE SET NULL'
            );
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable(self::TABLE_NAME)) {
            $this->preventEmptyMigrationWarning(false);

            return;
        }

        // AbstractMigration::addSql() rejects raw "DROP TABLE " statements
        // (accidental left-over-migration guard), so the drop goes through
        // the schema API instead - same as Version20260724210000. Both FKs
        // are defined on this table, so dropping it removes them too; the
        // hasTable() guard above already covers the whole operation.
        $schema->dropTable(self::TABLE_NAME);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
