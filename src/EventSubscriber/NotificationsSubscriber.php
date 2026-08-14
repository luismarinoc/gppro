<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\ExpenseRepository;
use App\Repository\InvoiceRepository;
use App\Repository\TimesheetRepository;
use KevinPapst\TablerBundle\Event\NotificationEvent;
use KevinPapst\TablerBundle\Model\NotificationModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Feeds the navbar bell with one aggregated notification per domain that has
 * pending approval work (design D9/A5-A10), pointing at the Approvals
 * Dashboard. Each domain's count is sourced from a COUNT()-only repository
 * method - no entity hydration - and isolated in its own try/catch (A7) so a
 * single failing domain never blanks out the others or 500s the whole app,
 * since this event fires in the global layout on every page.
 */
final class NotificationsSubscriber implements EventSubscriberInterface
{
    /** @var array{expense: int, invoice: int, timesheet: int}|null */
    private ?array $counts = null;

    public function __construct(
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ExpenseRepository $expenseRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NotificationEvent::class => ['onNotificationEvent', 100],
        ];
    }

    public function onNotificationEvent(NotificationEvent $event): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $counts = $this->getCounts($user);

        if ($counts['expense'] > 0) {
            $this->addDomainNotification($event, 'expense', $counts['expense']);
        }

        if ($counts['invoice'] > 0) {
            $this->addDomainNotification($event, 'invoice', $counts['invoice']);
        }

        if ($counts['timesheet'] > 0) {
            $this->addDomainNotification($event, 'timesheet', $counts['timesheet']);
        }
    }

    private function addDomainNotification(NotificationEvent $event, string $domain, int $count): void
    {
        $notification = new NotificationModel(
            'approvals_' . $domain,
            $this->translator->trans('approvals.notification.' . $domain, ['%count%' => $count]),
            'yellow'
        );
        $notification->setUrl($this->urlGenerator->generate('approvals_dashboard'));

        $event->addNotification($notification);
    }

    /**
     * Per-request memoization (design A8): a repeated NotificationEvent
     * dispatch within the same request must not re-run the queries.
     *
     * @return array{expense: int, invoice: int, timesheet: int}
     */
    private function getCounts(User $user): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $this->counts = [
            'expense' => $this->countSafely(fn (): int => $this->expenseRepository->countPendingForUser($user)),
            'invoice' => $this->countSafely(fn (): int => $this->invoiceRepository->countPendingPaymentApprovalForUser($user)),
            // The Timesheet count requires a 3-level join; only run it for a
            // teamlead, who is the only user tm.teamlead = true could ever
            // match anyway - zero behavior change, one join saved otherwise.
            'timesheet' => $user->isTeamlead()
                ? $this->countSafely(fn (): int => $this->timesheetRepository->countPendingApprovalForUser($user))
                : 0,
        ];

        return $this->counts;
    }

    /**
     * Isolates one domain's count query so a single failure never breaks the
     * bell for the other domains (design A7) - the bell renders on every
     * page, so an uncaught failure here would 500 the whole app.
     */
    private function countSafely(callable $counter): int
    {
        try {
            return $counter();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to compute pending-approval count for the navbar bell.', ['exception' => $e]);

            return 0;
        }
    }
}
