<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\Invoice;
use App\Entity\Timesheet;
use App\Repository\ExpenseRepository;
use App\Repository\InvoiceRepository;
use App\Repository\TimesheetRepository;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read-only, cross-domain aggregation of pending approvals (Expense +
 * Invoice payment + Timesheet). Navigation-only (proposal decision 7): no
 * inline approve/reject controls, no new write endpoints - every row links
 * into that domain's own existing approve/reject screen.
 *
 * Each domain's repository query used here is deliberately (Expense,
 * Invoice) or by design (Timesheet) not a complete visibility filter on its
 * own - see ExpenseRepository::findPendingForUser() and
 * InvoiceRepository::findPendingPaymentApprovalForUser(), both of which
 * only exclude the item's creator. This controller MUST re-check every
 * single item against that domain's real authorization
 * (is_granted()/voter/policy) before it is ever rendered - a raw repository
 * result is never trusted as the final visibility set.
 */
#[Route(path: '/approvals')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ApprovalsDashboardController extends AbstractController
{
    #[Route(path: '/', name: 'approvals_dashboard', methods: ['GET'])]
    public function index(
        ExpenseRepository $expenseRepository,
        TimesheetRepository $timesheetRepository,
        InvoiceRepository $invoiceRepository
    ): Response {
        $user = $this->getUser();

        $expenses = array_values(array_filter(
            $expenseRepository->findPendingForUser($user),
            fn (Expense $expense): bool => $this->isGranted('approve_expense', $expense)
        ));

        $timesheets = array_values(array_filter(
            $timesheetRepository->findPendingApprovalForUser($user),
            fn (Timesheet $timesheet): bool => $this->isGranted('approve_timesheet', $timesheet)
        ));

        $invoices = array_values(array_filter(
            $invoiceRepository->findPendingPaymentApprovalForUser($user),
            fn (Invoice $invoice): bool => $this->isGranted('approve_invoice_payment', $invoice)
        ));

        return $this->render('approvals_dashboard/index.html.twig', [
            'page_setup' => new PageSetup('approvals_dashboard.title'),
            'expenses' => $expenses,
            'timesheets' => $timesheets,
            'invoices' => $invoices,
        ]);
    }
}
