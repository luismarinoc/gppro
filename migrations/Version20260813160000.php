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

final class Version20260813160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an optional named approver to expense approval levels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expense_approval_levels ADD approver_user_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER ON gppro_expense_approval_levels (approver_user_id)');
        $this->addSql('ALTER TABLE gppro_expense_approval_levels ADD CONSTRAINT FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER FOREIGN KEY (approver_user_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expense_approval_levels DROP FOREIGN KEY FK_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER');
        $this->addSql('DROP INDEX IDX_GPPRO_EXPENSE_APPROVAL_LEVELS_APPROVER_USER ON gppro_expense_approval_levels');
        $this->addSql('ALTER TABLE gppro_expense_approval_levels DROP approver_user_id');
    }
}
