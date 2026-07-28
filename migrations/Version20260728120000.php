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
final class Version20260728120000 extends AbstractMigration
{
    private const TABLE_NAME = 'gppro_activities_board_state';
    private const TECHNICAL_USER_FK_NAME = 'FK_4C7B4B73BC15E5A5';
    private const FUNCTIONAL_USER_FK_NAME = 'FK_4C7B4B738D1B4ECC';

    public function getDescription(): string
    {
        return 'Add technical_user_id and functional_user_id to gppro_activities_board_state, for the Kanban board technical/functional assignee roles';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE_NAME);

        if (!$table->hasColumn('technical_user_id')) {
            $this->addSql(
                'ALTER TABLE ' . self::TABLE_NAME . ' ADD technical_user_id INT DEFAULT NULL, ADD functional_user_id INT DEFAULT NULL'
            );
        }

        if (!$table->hasIndex('IDX_GPPRO_ACTIVITIES_BOARD_TECHNICAL_USER')) {
            $this->addSql(
                'CREATE INDEX IDX_GPPRO_ACTIVITIES_BOARD_TECHNICAL_USER ON ' . self::TABLE_NAME . ' (technical_user_id)'
            );
        }

        if (!$table->hasIndex('IDX_GPPRO_ACTIVITIES_BOARD_FUNCTIONAL_USER')) {
            $this->addSql(
                'CREATE INDEX IDX_GPPRO_ACTIVITIES_BOARD_FUNCTIONAL_USER ON ' . self::TABLE_NAME . ' (functional_user_id)'
            );
        }

        if (!$table->hasForeignKey(self::TECHNICAL_USER_FK_NAME)) {
            $this->addSql(
                'ALTER TABLE ' . self::TABLE_NAME . ' ADD CONSTRAINT ' . self::TECHNICAL_USER_FK_NAME
                . ' FOREIGN KEY (technical_user_id) REFERENCES gppro_users (id) ON DELETE SET NULL'
            );
        }

        if (!$table->hasForeignKey(self::FUNCTIONAL_USER_FK_NAME)) {
            $this->addSql(
                'ALTER TABLE ' . self::TABLE_NAME . ' ADD CONSTRAINT ' . self::FUNCTIONAL_USER_FK_NAME
                . ' FOREIGN KEY (functional_user_id) REFERENCES gppro_users (id) ON DELETE SET NULL'
            );
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable(self::TABLE_NAME);

        if ($table->hasForeignKey(self::TECHNICAL_USER_FK_NAME)) {
            $this->addSql('ALTER TABLE ' . self::TABLE_NAME . ' DROP FOREIGN KEY ' . self::TECHNICAL_USER_FK_NAME);
        }

        if ($table->hasForeignKey(self::FUNCTIONAL_USER_FK_NAME)) {
            $this->addSql('ALTER TABLE ' . self::TABLE_NAME . ' DROP FOREIGN KEY ' . self::FUNCTIONAL_USER_FK_NAME);
        }

        if ($table->hasColumn('technical_user_id')) {
            $this->addSql('ALTER TABLE ' . self::TABLE_NAME . ' DROP COLUMN technical_user_id, DROP COLUMN functional_user_id');
        }

        $this->preventEmptyMigrationWarning(false);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
