<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\NotificationsSubscriber;
use App\Repository\ExpenseRepository;
use App\Repository\InvoiceRepository;
use App\Repository\TimesheetRepository;
use KevinPapst\TablerBundle\Event\NotificationEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Covers the aggregated navbar bell (design A5-A10): one notification per
 * domain with pending work, sourced from the COUNT-only repository methods,
 * pointing at the Approvals Dashboard - never one entry per pending item
 * (D9), and never letting one domain's failure blank out the others (A7).
 */
#[CoversClass(NotificationsSubscriber::class)]
class NotificationsSubscriberTest extends TestCase
{
    private function createSubscriber(
        Security $security,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        ExpenseRepository $expenseRepository,
        InvoiceRepository $invoiceRepository,
        TimesheetRepository $timesheetRepository,
        LoggerInterface $logger
    ): NotificationsSubscriber {
        return new NotificationsSubscriber(
            $security,
            $translator,
            $urlGenerator,
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $logger
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = NotificationsSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(NotificationEvent::class, $events);
        $methodName = $events[NotificationEvent::class][0];
        self::assertIsString($methodName);
        self::assertTrue(method_exists(NotificationsSubscriber::class, $methodName));
    }

    public function testNoUserAddsNoNotification(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $expenseRepository = $this->createMock(ExpenseRepository::class);
        $expenseRepository->expects($this->never())->method('countPendingForUser');
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->expects($this->never())->method('countPendingPaymentApprovalForUser');
        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->expects($this->never())->method('countPendingApprovalForUser');

        $subscriber = $this->createSubscriber(
            $security,
            $this->createMock(TranslatorInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $this->createMock(LoggerInterface::class)
        );

        $event = new NotificationEvent();
        $subscriber->onNotificationEvent($event);

        self::assertSame(0, $event->getTotal());
        self::assertTrue($event->isShowBadgeTotal(), 'setShowBadgeTotal(false) must never be called unconditionally');
    }

    public function testAllCountsZeroAddsNoNotification(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTeamlead')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $expenseRepository = $this->createMock(ExpenseRepository::class);
        $expenseRepository->method('countPendingForUser')->with($user)->willReturn(0);
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->method('countPendingPaymentApprovalForUser')->with($user)->willReturn(0);
        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->method('countPendingApprovalForUser')->with($user)->willReturn(0);

        $subscriber = $this->createSubscriber(
            $security,
            $this->createMock(TranslatorInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $this->createMock(LoggerInterface::class)
        );

        $event = new NotificationEvent();
        $subscriber->onNotificationEvent($event);

        self::assertSame(0, $event->getTotal());
        self::assertTrue($event->isShowBadgeTotal());
    }

    public function testEachDomainWithPendingCountAddsOneAggregatedEntry(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTeamlead')->willReturn(false);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $expenseRepository = $this->createMock(ExpenseRepository::class);
        $expenseRepository->expects($this->once())->method('countPendingForUser')->with($user)->willReturn(3);
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->expects($this->once())->method('countPendingPaymentApprovalForUser')->with($user)->willReturn(2);
        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->expects($this->never())->method('countPendingApprovalForUser');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['approvals.notification.expense', ['%count%' => 3], null, null, 'You have 3 pending expenses'],
            ['approvals.notification.invoice', ['%count%' => 2], null, null, 'You have 2 pending invoice payments'],
            ['approvals.notification.title', [], null, null, 'Pending approvals'],
        ]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('approvals_dashboard')->willReturn('/approvals/');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $subscriber = $this->createSubscriber(
            $security,
            $translator,
            $urlGenerator,
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $logger
        );

        $event = new NotificationEvent();
        $subscriber->onNotificationEvent($event);

        $notifications = array_values($event->getNotifications(null));
        self::assertCount(2, $notifications);

        self::assertSame('You have 3 pending expenses', $notifications[0]->getMessage());
        self::assertSame('/approvals/', $notifications[0]->getUrl());

        self::assertSame('You have 2 pending invoice payments', $notifications[1]->getMessage());
        self::assertSame('/approvals/', $notifications[1]->getUrl());

        self::assertTrue($event->isShowBadgeTotal());
        self::assertSame('Pending approvals', $event->getTitle(), 'The dropdown title (design A9) must be set from approvals.notification.title');
    }

    public function testNonTeamleadUserNeverCallsTimesheetCountMethod(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTeamlead')->willReturn(false);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $expenseRepository = $this->createMock(ExpenseRepository::class);
        $expenseRepository->method('countPendingForUser')->willReturn(0);
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->method('countPendingPaymentApprovalForUser')->willReturn(0);
        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->expects($this->never())->method('countPendingApprovalForUser');

        $subscriber = $this->createSubscriber(
            $security,
            $this->createMock(TranslatorInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $this->createMock(LoggerInterface::class)
        );

        $subscriber->onNotificationEvent(new NotificationEvent());
    }

    public function testOneRepositoryThrowingStillEmitsOtherDomains(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isTeamlead')->willReturn(true);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $expenseRepository = $this->createMock(ExpenseRepository::class);
        $expenseRepository->method('countPendingForUser')->willThrowException(new \RuntimeException('expense query failed'));
        $invoiceRepository = $this->createMock(InvoiceRepository::class);
        $invoiceRepository->method('countPendingPaymentApprovalForUser')->with($user)->willReturn(1);
        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->method('countPendingApprovalForUser')->with($user)->willReturn(1);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['approvals.notification.invoice', ['%count%' => 1], null, null, 'invoice msg'],
            ['approvals.notification.timesheet', ['%count%' => 1], null, null, 'timesheet msg'],
            ['approvals.notification.title', [], null, null, 'Pending approvals'],
        ]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('approvals_dashboard')->willReturn('/approvals/');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $subscriber = $this->createSubscriber(
            $security,
            $translator,
            $urlGenerator,
            $expenseRepository,
            $invoiceRepository,
            $timesheetRepository,
            $logger
        );

        $event = new NotificationEvent();
        $subscriber->onNotificationEvent($event);

        $notifications = array_values($event->getNotifications(null));
        self::assertCount(2, $notifications, 'Expense failure must not block Invoice/Timesheet notifications');
        self::assertSame('invoice msg', $notifications[0]->getMessage());
        self::assertSame('timesheet msg', $notifications[1]->getMessage());
    }
}
