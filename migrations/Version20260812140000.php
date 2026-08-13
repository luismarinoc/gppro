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

final class Version20260812140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expenses, expense allocations, expense approval levels, and expense approvals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE gppro_expenses (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, source_expense_id INT DEFAULT NULL, description LONGTEXT NOT NULL, amount INT NOT NULL, expense_date DATE NOT NULL, recurrence VARCHAR(10) DEFAULT NULL, status VARCHAR(20) DEFAULT 'draft' NOT NULL, required_levels INT DEFAULT NULL, current_level INT DEFAULT 0 NOT NULL, period_key VARCHAR(7) DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, INDEX IDX_GPPRO_EXPENSES_CREATED_BY (created_by_id), INDEX IDX_GPPRO_EXPENSES_SOURCE (source_expense_id), UNIQUE INDEX UNIQ_GPPRO_EXPENSES_SOURCE_PERIOD (source_expense_id, period_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE gppro_expense_allocations (id INT AUTO_INCREMENT NOT NULL, expense_id INT NOT NULL, project_id INT NOT NULL, quotation_line_id INT DEFAULT NULL, percentage NUMERIC(5, 2) NOT NULL, amount_clp INT DEFAULT NULL, charged TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_GPPRO_EXPENSE_ALLOCATIONS_EXPENSE (expense_id), INDEX IDX_GPPRO_EXPENSE_ALLOCATIONS_PROJECT (project_id), UNIQUE INDEX UNIQ_GPPRO_EXPENSE_ALLOCATIONS_QUOTATION_LINE (quotation_line_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gppro_expense_approval_levels (id INT AUTO_INCREMENT NOT NULL, level INT NOT NULL, min_amount INT NOT NULL, required_role VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_GPPRO_EXPENSE_APPROVAL_LEVELS_LEVEL (level), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gppro_expense_approvals (id INT AUTO_INCREMENT NOT NULL, expense_id INT NOT NULL, approved_by_id INT DEFAULT NULL, level INT NOT NULL, decision VARCHAR(20) NOT NULL, approved_at DATETIME NOT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_GPPRO_EXPENSE_APPROVALS_APPROVED_BY (approved_by_id), UNIQUE INDEX UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_LEVEL (expense_id, level), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_expenses ADD CONSTRAINT FK_GPPRO_EXPENSES_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gppro_expenses ADD CONSTRAINT FK_GPPRO_EXPENSES_SOURCE FOREIGN KEY (source_expense_id) REFERENCES gppro_expenses (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gppro_expense_allocations ADD CONSTRAINT FK_GPPRO_EXPENSE_ALLOCATIONS_EXPENSE FOREIGN KEY (expense_id) REFERENCES gppro_expenses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_expense_allocations ADD CONSTRAINT FK_GPPRO_EXPENSE_ALLOCATIONS_PROJECT FOREIGN KEY (project_id) REFERENCES gppro_projects (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE gppro_expense_allocations ADD CONSTRAINT FK_GPPRO_EXPENSE_ALLOCATIONS_QUOTATION_LINE FOREIGN KEY (quotation_line_id) REFERENCES gppro_quotation_lines (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gppro_expense_approvals ADD CONSTRAINT FK_GPPRO_EXPENSE_APPROVALS_EXPENSE FOREIGN KEY (expense_id) REFERENCES gppro_expenses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_expense_approvals ADD CONSTRAINT FK_GPPRO_EXPENSE_APPROVALS_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
        $this->addSql("INSERT INTO gppro_expense_approval_levels (level, min_amount, required_role) VALUES (1, 0, 'ROLE_TEAMLEAD')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_expense_allocations DROP FOREIGN KEY FK_GPPRO_EXPENSE_ALLOCATIONS_EXPENSE');
        $this->addSql('ALTER TABLE gppro_expense_allocations DROP FOREIGN KEY FK_GPPRO_EXPENSE_ALLOCATIONS_PROJECT');
        $this->addSql('ALTER TABLE gppro_expense_allocations DROP FOREIGN KEY FK_GPPRO_EXPENSE_ALLOCATIONS_QUOTATION_LINE');
        $this->addSql('ALTER TABLE gppro_expense_approvals DROP FOREIGN KEY FK_GPPRO_EXPENSE_APPROVALS_EXPENSE');
        $this->addSql('ALTER TABLE gppro_expense_approvals DROP FOREIGN KEY FK_GPPRO_EXPENSE_APPROVALS_APPROVED_BY');
        $this->addSql('ALTER TABLE gppro_expenses DROP FOREIGN KEY FK_GPPRO_EXPENSES_CREATED_BY');
        $this->addSql('ALTER TABLE gppro_expenses DROP FOREIGN KEY FK_GPPRO_EXPENSES_SOURCE');
        $this->addSql('DROP TABLE gppro_expense_approvals');
        $this->addSql('DROP TABLE gppro_expense_allocations');
        $this->addSql('DROP TABLE gppro_expense_approval_levels');
        $this->addSql('DROP TABLE gppro_expenses');
    }
}
