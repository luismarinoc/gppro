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

final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow rejected expenses to be resubmitted with preserved approval history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expenses ADD approval_attempt INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE gppro_expense_approvals ADD approval_attempt INT NOT NULL DEFAULT 1');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_ATTEMPT_LEVEL ON gppro_expense_approvals (expense_id, approval_attempt, level)');
        $this->addSql('DROP INDEX UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_LEVEL ON gppro_expense_approvals');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_ATTEMPT_LEVEL ON gppro_expense_approvals');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_LEVEL ON gppro_expense_approvals (expense_id, level)');
        $this->addSql('ALTER TABLE gppro_expense_approvals DROP approval_attempt');
        $this->addSql('ALTER TABLE gppro_expenses DROP approval_attempt');
    }
}
