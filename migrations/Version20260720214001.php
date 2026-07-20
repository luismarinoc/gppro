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
final class Version20260720214001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename kimai2_* database tables to gppro_* (schema only, no data changes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                RENAME TABLE
                kimai2_access_token TO gppro_access_token,
                kimai2_activities TO gppro_activities,
                kimai2_activities_meta TO gppro_activities_meta,
                kimai2_activities_rates TO gppro_activities_rates,
                kimai2_activities_teams TO gppro_activities_teams,
                kimai2_bookmarks TO gppro_bookmarks,
                kimai2_configuration TO gppro_configuration,
                kimai2_customers TO gppro_customers,
                kimai2_customers_comments TO gppro_customers_comments,
                kimai2_customers_meta TO gppro_customers_meta,
                kimai2_customers_rates TO gppro_customers_rates,
                kimai2_customers_teams TO gppro_customers_teams,
                kimai2_export_templates TO gppro_export_templates,
                kimai2_invoice_templates TO gppro_invoice_templates,
                kimai2_invoice_templates_meta TO gppro_invoice_templates_meta,
                kimai2_invoices TO gppro_invoices,
                kimai2_invoices_meta TO gppro_invoices_meta,
                kimai2_milestones TO gppro_milestones,
                kimai2_projects TO gppro_projects,
                kimai2_projects_comments TO gppro_projects_comments,
                kimai2_projects_meta TO gppro_projects_meta,
                kimai2_projects_rates TO gppro_projects_rates,
                kimai2_projects_teams TO gppro_projects_teams,
                kimai2_roles TO gppro_roles,
                kimai2_roles_permissions TO gppro_roles_permissions,
                kimai2_sessions TO gppro_sessions,
                kimai2_tags TO gppro_tags,
                kimai2_teams TO gppro_teams,
                kimai2_timesheet TO gppro_timesheet,
                kimai2_timesheet_meta TO gppro_timesheet_meta,
                kimai2_timesheet_tags TO gppro_timesheet_tags,
                kimai2_user_preferences TO gppro_user_preferences,
                kimai2_users TO gppro_users,
                kimai2_users_teams TO gppro_users_teams,
                kimai2_working_times TO gppro_working_times
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                RENAME TABLE
                gppro_access_token TO kimai2_access_token,
                gppro_activities TO kimai2_activities,
                gppro_activities_meta TO kimai2_activities_meta,
                gppro_activities_rates TO kimai2_activities_rates,
                gppro_activities_teams TO kimai2_activities_teams,
                gppro_bookmarks TO kimai2_bookmarks,
                gppro_configuration TO kimai2_configuration,
                gppro_customers TO kimai2_customers,
                gppro_customers_comments TO kimai2_customers_comments,
                gppro_customers_meta TO kimai2_customers_meta,
                gppro_customers_rates TO kimai2_customers_rates,
                gppro_customers_teams TO kimai2_customers_teams,
                gppro_export_templates TO kimai2_export_templates,
                gppro_invoice_templates TO kimai2_invoice_templates,
                gppro_invoice_templates_meta TO kimai2_invoice_templates_meta,
                gppro_invoices TO kimai2_invoices,
                gppro_invoices_meta TO kimai2_invoices_meta,
                gppro_milestones TO kimai2_milestones,
                gppro_projects TO kimai2_projects,
                gppro_projects_comments TO kimai2_projects_comments,
                gppro_projects_meta TO kimai2_projects_meta,
                gppro_projects_rates TO kimai2_projects_rates,
                gppro_projects_teams TO kimai2_projects_teams,
                gppro_roles TO kimai2_roles,
                gppro_roles_permissions TO kimai2_roles_permissions,
                gppro_sessions TO kimai2_sessions,
                gppro_tags TO kimai2_tags,
                gppro_teams TO kimai2_teams,
                gppro_timesheet TO kimai2_timesheet,
                gppro_timesheet_meta TO kimai2_timesheet_meta,
                gppro_timesheet_tags TO kimai2_timesheet_tags,
                gppro_user_preferences TO kimai2_user_preferences,
                gppro_users TO kimai2_users,
                gppro_users_teams TO kimai2_users_teams,
                gppro_working_times TO kimai2_working_times
            SQL);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
