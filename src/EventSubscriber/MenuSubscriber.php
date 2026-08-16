<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use KevinPapst\TablerBundle\Helper\ContextHelper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ContextHelper $helper
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMainMenuConfigure', 100],
        ];
    }

    private function addDivider(MenuItemModel $menu): void
    {
        if ($this->helper->isBoxedLayout()) {
            $menu->addChild(MenuItemModel::createDivider());
        }
    }

    public function onMainMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        $auth = $this->security;

        if (!$auth->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return;
        }

        // main menu
        $menu = $event->getMenu();
        /** @var User $user */
        $user = $auth->getUser();

        $menu->addChild(new MenuItemModel('dashboard', 'dashboard.title', 'dashboard', [], 'dashboard'));
        $menu->addChild(new MenuItemModel('favorites', 'favorite_routes', null, [], 'bookmarked'));

        // ------------------- timesheet menu -------------------
        $times = new MenuItemModel('times', 'time_tracking', null, [], 'timesheet');

        if ($auth->isGranted('view_own_timesheet')) {
            $timesheets = new MenuItemModel('timesheet', 'my_times', 'timesheet', [], 'timesheet');
            $timesheets->setChildRoutes(['timesheet_export', 'timesheet_edit', 'timesheet_create', 'timesheet_multi_update']);
            $times->addChild($timesheets);

            if ($auth->isGranted('quick-entry')) {
                $times->addChild(
                    new MenuItemModel('quick_entry', 'quick_entry.title', 'quick_entry', [], 'weekly-times')
                );
            }

            $times->addChild(
                new MenuItemModel('calendar', 'calendar', 'calendar', [], 'calendar')
            );
        }

        $this->addDivider($times);

        if ($auth->isGranted('create_export')) {
            $times->addChild(
                new MenuItemModel('export', 'export', 'export', [], 'export')
            );
        }

        if ($auth->isGranted('view_other_timesheet')) {
            $timesheets = new MenuItemModel('timesheet_admin', 'all_times', 'admin_timesheet', [], 'timesheet-team');
            $timesheets->setChildRoutes(['admin_timesheet_export', 'admin_timesheet_edit', 'admin_timesheet_create', 'admin_timesheet_multi_update']);
            $times->addChild($timesheets);
        }

        if ($times->hasChildren()) {
            $times->setExpanded(true); // Kimai is all about time-tracking, so we expand this menu always
            $menu->addChild($times);
        }

        $contract = new MenuItemModel('contract', 'work_contract', null, [], 'contract');
        if ($auth->isGranted('hours', $user)) {
            $contract->addChild(new MenuItemModel('contract_status', 'work_times', 'user_contract', [], 'work_times'));
        }

        if ($contract->hasChildren()) {
            $menu->addChild($contract);
        }

        if ($auth->isGranted('view_reporting')) {
            $reporting = new MenuItemModel('reporting', 'menu.reporting', 'reporting', [], 'reporting');
            $reporting->setChildRoutes(['report_user_week', 'report_user_month', 'report_weekly_users', 'report_monthly_users', 'report_project_view']);
            $menu->addChild($reporting);
        }

        $quotations = new MenuItemModel('quotations', 'quotations', null, [], 'file-invoice');
        if ($auth->isGranted('create_quotation')) {
            $quotationCreate = new MenuItemModel('quotation_create', 'quotation_quote', 'quotation_create', [], 'file-invoice');
            $quotations->addChild($quotationCreate);
        }
        if ($auth->isGranted('view_quotation')) {
            $quotationList = new MenuItemModel('quotation_list', 'quotation_history', 'quotation_list', [], 'list');
            $quotationList->setChildRoutes(['quotation_edit', 'quotation_view', 'quotation_send', 'quotation_convert']);
            $quotations->addChild($quotationList);
        }
        if ($auth->isGranted('manage_quotation_catalog')) {
            $catalog = new MenuItemModel('quotation_catalog', 'quotation_catalog', 'admin_quotation_catalog', [], 'file-invoice-dollar');
            $catalog->setChildRoutes(['admin_quotation_catalog_create', 'admin_quotation_catalog_edit', 'admin_quotation_catalog_delete']);
            $quotations->addChild($catalog);
        }
        if ($quotations->hasChildren()) {
            $menu->addChild($quotations);
        }

        // ------------------- expense menu -------------------
        // "Pending my approval" is deliberately NOT duplicated here: it is
        // the same query as approvals_dashboard (see that controller's own
        // docblock), which already aggregates it alongside timesheet and
        // invoice approvals in one place. The expense_pending route/page
        // still exists and stays reachable from the expense list itself
        // (templates/expense/index.html.twig), just not from this menu.
        $expenses = new MenuItemModel('expenses', 'expenses', null, [], 'receipt');
        if ($auth->isGranted('create_expense')) {
            $expenseCreate = new MenuItemModel('expense_create', 'expense_create', 'expense_create', [], 'receipt');
            $expenses->addChild($expenseCreate);
        }
        if ($auth->isGranted('view_expense')) {
            $expenseList = new MenuItemModel('expense_list', 'expense_list', 'expense_list', [], 'receipt');
            $expenseList->setChildRoutes(['expense_edit', 'expense_view', 'expense_submit', 'expense_approve', 'expense_reject', 'expense_delete', 'expense_allocation_charge', 'expense_pending']);
            $expenses->addChild($expenseList);
        }
        if ($expenses->hasChildren()) {
            $menu->addChild($expenses);
        }

        // ------------------- invoice menu -------------------
        $invoice = new MenuItemModel('invoices', 'invoices', null, [], 'invoice');

        if ($auth->isGranted('create_invoice')) {
            $tmpMenu = new MenuItemModel('invoice', 'invoice_form.title', 'invoice', [], 'invoice');
            $tmpMenu->setChildRoutes(['milestone_invoice_customers', 'milestone_invoice_index', 'milestone_invoice_create']);
            $invoice->addChild($tmpMenu);
        }

        if ($auth->isGranted('view_invoice')) {
            $tmpMenu = new MenuItemModel('invoice_listing', 'all_invoices', 'admin_invoice_list', [], 'list');
            $tmpMenu->setChildRoutes(['admin_invoice_edit']);
            $invoice->addChild($tmpMenu);
        }

        if ($auth->isGranted('manage_invoice_template')) {
            $tmpMenu = new MenuItemModel('invoice-template', 'admin_invoice_template.title', 'admin_invoice_template', [], 'invoice-template');
            $tmpMenu->setChildRoutes(['admin_invoice_template_edit', 'admin_invoice_template_create', 'admin_invoice_template_copy', 'admin_invoice_document_upload']);
            $invoice->addChild($tmpMenu);
        }

        if ($invoice->hasChildren()) {
            $this->addDivider($invoice);
        }

        $menu->addChild($invoice);

        // ------------------- approvals menu -------------------
        // Single top-level home for approval work (dashboard) and approval
        // configuration (expense/invoice levels), relocated out of their
        // former domain menus (proposal D1/D2). The dashboard child is
        // gated only by IS_AUTHENTICATED_FULLY - matching the controller's
        // own guard exactly - so it is visible to any authenticated user,
        // not only to those holding a manage_* permission (D6).
        $approvals = new MenuItemModel('approvals', 'menu.approvals', null, [], 'review');

        if ($auth->isGranted('IS_AUTHENTICATED_FULLY')) {
            $approvals->addChild(
                new MenuItemModel('approvals_dashboard', 'approvals_dashboard.title', 'approvals_dashboard', [], 'clock')
            );
        }

        if ($auth->isGranted('manage_expense_approval_levels')) {
            $approvalLevels = new MenuItemModel('expense_approval_level_list', 'expense_approval_level', 'admin_expense_approval_level_list', [], 'settings');
            $approvalLevels->setChildRoutes(['admin_expense_approval_level_create', 'admin_expense_approval_level_edit', 'admin_expense_approval_level_delete']);
            $approvals->addChild($approvalLevels);
        }

        if ($auth->isGranted('manage_invoice_payment_approval_levels')) {
            $approvalLevels = new MenuItemModel('invoice_payment_approval_level_list', 'invoice_payment_approval_level', 'admin_invoice_payment_approval_level_list', [], 'settings');
            $approvalLevels->setChildRoutes(['admin_invoice_payment_approval_level_create', 'admin_invoice_payment_approval_level_edit', 'admin_invoice_payment_approval_level_delete']);
            $approvals->addChild($approvalLevels);
        }

        if ($approvals->hasChildren()) {
            $menu->addChild($approvals);
        }

        // ------------------- admin menu -------------------
        $menu = $event->getAdminMenu();

        if ($auth->isGranted('view_customer') || $auth->isGranted('view_teamlead_customer') || $auth->isGranted('view_team_customer')) {
            $customers = new MenuItemModel('customers', 'customers', 'admin_customer', [], 'customer');
            $customers->setChildRoutes(['admin_customer_create', 'admin_customer_permissions', 'customer_details', 'admin_customer_edit', 'admin_customer_delete']);
            $menu->addChild($customers);
        }

        if ($auth->isGranted('view_project') || $auth->isGranted('view_teamlead_project') || $auth->isGranted('view_team_project')) {
            $projects = new MenuItemModel('projects', 'projects', 'admin_project', [], 'project');
            $projects->setChildRoutes(['admin_project_permissions', 'admin_project_create', 'project_details', 'admin_project_edit', 'admin_project_delete']);
            $menu->addChild($projects);
        }

        if ($auth->isGranted('view_project') || $auth->isGranted('view_teamlead_project') || $auth->isGranted('view_team_project')) {
            $activityBoard = new MenuItemModel('activity_board', 'activity_board.title', 'admin_project_board_picker', [], 'columns');
            $activityBoard->setChildRoutes(['admin_project_board_picker_paginated', 'project_board']);
            $menu->addChild($activityBoard);

            // "Actividades" opens a project picker, then the 3-panel
            // workspace scoped to the selected project (proposal A1) - the
            // picker renders the project list, so it must live under the
            // project view guard, not the activity guard.
            $activities = new MenuItemModel('activities', 'activities', 'admin_project_activity_workspace_picker', [], 'activity');
            $activities->setChildRoutes(['admin_project_activity_workspace_picker_paginated', 'project_activity_workspace']);
            $menu->addChild($activities);
        }

        if ($auth->isGranted('view_activity') || $auth->isGranted('view_teamlead_activity') || $auth->isGranted('view_team_activity')) {
            // "Todas las actividades" - the pre-existing cross-project list,
            // unchanged route, permission, and CRUD child routes (Rule 10).
            $activitiesAll = new MenuItemModel('activities_all', 'activities_all', 'admin_activity', [], 'activity');
            $activitiesAll->setChildRoutes(['admin_activity_create', 'activity_details', 'admin_activity_edit', 'admin_activity_delete']);
            $menu->addChild($activitiesAll);
        }

        if ($auth->isGranted('view_tag')) {
            $menu->addChild(
                new MenuItemModel('tags', 'tags', 'tags', [], 'fas fa-tags')
            );
        }

        if ($auth->isGranted('view_fx_rate')) {
            $fxRates = new MenuItemModel('fx_rates', 'fx_rates', 'fx_rates', [], 'fas fa-coins');
            $fxRates->setChildRoutes(['fx_rates_create', 'fx_rates_edit']);
            $menu->addChild($fxRates);
        }

        $this->addDivider($menu);

        // ------------------- system menu -------------------
        $menu = $event->getSystemMenu();

        if ($auth->isGranted('view_user')) {
            $users = new MenuItemModel('users', 'users', 'admin_user', [], 'users');
            $users->setChildRoutes(['admin_user_create', 'admin_user_delete',  'user_profile', 'user_profile_edit', 'user_profile_password', 'user_profile_api_token', 'user_profile_roles', 'user_profile_preferences', 'user_profile_2fa']);
            $menu->addChild($users);
        }

        if ($auth->isGranted('role_permissions')) {
            $users = new MenuItemModel('roles', 'profile.roles', 'admin_user_permissions', [], 'permissions');
            $menu->addChild($users);
        }

        if ($auth->isGranted('view_team')) {
            $teams = new MenuItemModel('teams', 'teams', 'admin_team', [], 'team');
            $teams->setChildRoutes(['admin_team_create', 'admin_team_edit']);
            $menu->addChild($teams);
        }

        if ($auth->isGranted('ROLE_SUPER_ADMIN')) {
            $menu->addChild(
                new MenuItemModel('login_audit', 'login_audit', 'admin_login_audit', [], 'shield-lock')
            );
        }

        if ($menu->hasChildren()) {
            $this->addDivider($menu);
        }

        if ($auth->isGranted('plugins')) {
            $menu->addChild(
                new MenuItemModel('plugins', 'menu.plugin', 'plugins', [], 'plugin')
            );
        }

        if ($auth->isGranted('system_configuration')) {
            $systemConfig = new MenuItemModel('configurations', 'menu.system_configuration', 'system_configuration', [], 'configuration');
            $systemConfig->setChildRoutes(['system_configuration_update', 'system_configuration_section']);
            $menu->addChild($systemConfig);
        }

        if ($auth->isGranted('system_information')) {
            $menu->addChild(
                new MenuItemModel('doctor', 'Doctor', 'doctor', [], 'doctor')
            );
        }

        $this->addDivider($menu);
    }
}
