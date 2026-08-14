<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\Event\ConfigureMainMenuEvent;
use App\EventSubscriber\MenuSubscriber;
use KevinPapst\TablerBundle\Helper\ContextHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[CoversClass(MenuSubscriber::class)]
class MenuSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = MenuSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(ConfigureMainMenuEvent::class, $events);
        $methodName = $events[ConfigureMainMenuEvent::class][0];
        self::assertIsString($methodName);
        self::assertTrue(method_exists(MenuSubscriber::class, $methodName));
    }

    public function testQuotationMenuIsVisibleForUsersWithViewPermission(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_quotation'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $quotation = $event->getMenu()->findChild('quotations');
        self::assertNotNull($quotation);
        self::assertNull($quotation->getRoute());
        $quotationList = $quotation->findChild('quotation_list');
        self::assertNotNull($quotationList);
        self::assertSame('quotation_list', $quotationList->getRoute());
        self::assertTrue($quotationList->isChildRoute('quotation_create'));
        self::assertTrue($quotationList->isChildRoute('quotation_edit'));
        self::assertTrue($quotationList->isChildRoute('quotation_view'));
        self::assertTrue($quotationList->isChildRoute('quotation_send'));
        self::assertTrue($quotationList->isChildRoute('quotation_convert'));
    }

    public function testQuotationCatalogMenuIsVisibleForCatalogManagers(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'manage_quotation_catalog'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $catalog = $event->getMenu()->findChild('quotation_catalog');
        self::assertNotNull($catalog);
        self::assertSame('admin_quotation_catalog', $catalog->getRoute());
        self::assertTrue($catalog->isChildRoute('admin_quotation_catalog_create'));
    }

    public function testExpenseMenuIsVisibleForUsersWithViewPermission(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_expense'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $expenses = $event->getMenu()->findChild('expenses');
        self::assertNotNull($expenses);
        self::assertNull($expenses->getRoute());

        $expenseList = $expenses->findChild('expense_list');
        self::assertNotNull($expenseList);
        self::assertSame('expense_list', $expenseList->getRoute());
        self::assertTrue($expenseList->isChildRoute('expense_create'));
        self::assertTrue($expenseList->isChildRoute('expense_edit'));
        self::assertTrue($expenseList->isChildRoute('expense_view'));
        self::assertTrue($expenseList->isChildRoute('expense_submit'));
        self::assertTrue($expenseList->isChildRoute('expense_approve'));
        self::assertTrue($expenseList->isChildRoute('expense_reject'));
        self::assertTrue($expenseList->isChildRoute('expense_delete'));
        self::assertTrue($expenseList->isChildRoute('expense_allocation_charge'));

        $expensePending = $expenses->findChild('expense_pending');
        self::assertNotNull($expensePending);
        self::assertSame('expense_pending', $expensePending->getRoute());

        self::assertNull($expenses->findChild('expense_approval_level_list'));
    }

    public function testActivitiesMenuPointsToWorkspacePickerUnderViewProjectPermission(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_project'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $activities = $event->getAdminMenu()->findChild('activities');
        self::assertNotNull($activities);
        self::assertSame('admin_project_activity_workspace_picker', $activities->getRoute());
        self::assertTrue($activities->isChildRoute('admin_project_activity_workspace_picker_paginated'));
        self::assertTrue($activities->isChildRoute('project_activity_workspace'));

        self::assertNull($event->getAdminMenu()->findChild('activities_all'));
    }

    public function testActivitiesAllMenuKeepsGlobalListUnderViewActivityPermission(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_activity'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $activitiesAll = $event->getAdminMenu()->findChild('activities_all');
        self::assertNotNull($activitiesAll);
        self::assertSame('admin_activity', $activitiesAll->getRoute());
        self::assertTrue($activitiesAll->isChildRoute('admin_activity_create'));
        self::assertTrue($activitiesAll->isChildRoute('activity_details'));
        self::assertTrue($activitiesAll->isChildRoute('admin_activity_edit'));
        self::assertTrue($activitiesAll->isChildRoute('admin_activity_delete'));

        self::assertNull($event->getAdminMenu()->findChild('activities'));
    }

    public function testExpenseApprovalLevelMenuIsVisibleForLevelManagers(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'manage_expense_approval_levels'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $expenses = $event->getMenu()->findChild('expenses');
        self::assertNotNull($expenses);

        $levels = $expenses->findChild('expense_approval_level_list');
        self::assertNotNull($levels);
        self::assertSame('admin_expense_approval_level_list', $levels->getRoute());
        self::assertTrue($levels->isChildRoute('admin_expense_approval_level_create'));
        self::assertTrue($levels->isChildRoute('admin_expense_approval_level_edit'));
        self::assertTrue($levels->isChildRoute('admin_expense_approval_level_delete'));

        self::assertNull($expenses->findChild('expense_list'));
    }

    public function testInvoicePaymentApprovalLevelMenuIsVisibleForLevelManagers(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'manage_invoice_payment_approval_levels'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $invoice = $event->getMenu()->findChild('invoices');
        self::assertNotNull($invoice);

        $levels = $invoice->findChild('invoice_payment_approval_level_list');
        self::assertNotNull($levels);
        self::assertSame('admin_invoice_payment_approval_level_list', $levels->getRoute());
        self::assertTrue($levels->isChildRoute('admin_invoice_payment_approval_level_create'));
        self::assertTrue($levels->isChildRoute('admin_invoice_payment_approval_level_edit'));
        self::assertTrue($levels->isChildRoute('admin_invoice_payment_approval_level_delete'));

        self::assertNull($invoice->findChild('invoice_listing'));
    }

    public function testLoginAuditMenuIsVisibleForSuperAdmins(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'ROLE_SUPER_ADMIN'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $loginAudit = $event->getSystemMenu()->findChild('login_audit');
        self::assertNotNull($loginAudit);
        self::assertSame('admin_login_audit', $loginAudit->getRoute());
    }

    public function testLoginAuditMenuIsHiddenForNonSuperAdmins(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_user'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        self::assertNull($event->getSystemMenu()->findChild('login_audit'));
    }
}
