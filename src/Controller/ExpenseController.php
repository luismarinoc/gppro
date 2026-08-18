<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Expense\AllocationPercentageValidator;
use App\Expense\ExpenseAllocationAmountUpdater;
use App\Expense\ExpenseApprovalPolicy;
use App\Expense\ExpenseApprovalService;
use App\Expense\ExpenseCrossChargeService;
use App\Form\ExpenseApprovalDecisionForm;
use App\Form\ExpenseChargeForm;
use App\Form\ExpenseForm;
use App\Repository\ExpenseRepository;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/expense')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('view_expense')]
final class ExpenseController extends AbstractController
{
    #[Route(path: '/', name: 'expense_list', methods: ['GET'])]
    public function index(Request $request, ExpenseRepository $repository, ExpenseApprovalPolicy $approvalPolicy): Response
    {
        $status = $request->query->get('status');
        $status = \is_string($status) ? $status : null;
        $user = $this->getUser();

        $expenses = $repository->findForListing($user, $status);

        // Design D4: the approver-eligible branch ((b) of the visibility
        // rule) is merged here, not in ExpenseRepository, because that
        // repository is Doctrine-factory-registered (services.yaml) and
        // cannot have ExpenseApprovalPolicy constructor-injected. Reuses the
        // existing findPendingForUser() query, filtered through the
        // unmodified canApprove() policy. Only merged when the current
        // status filter could actually include a pending-approval expense.
        if (null === $status || Expense::STATUS_PENDING_APPROVAL === $status) {
            $seen = [];
            foreach ($expenses as $expense) {
                $seen[$expense->getId()] = true;
            }

            foreach ($repository->findPendingForUser($user) as $pending) {
                if (isset($seen[$pending->getId()]) || !$approvalPolicy->canApprove($pending, $user)) {
                    continue;
                }

                $expenses[] = $pending;
                $seen[$pending->getId()] = true;
            }

            usort($expenses, static fn (Expense $a, Expense $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());
        }

        return $this->render('expense/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'expenses' => $expenses,
            'status' => $status,
        ]);
    }

    #[Route(path: '/pending', name: 'expense_pending', methods: ['GET'])]
    public function pending(ExpenseRepository $repository): Response
    {
        return $this->render('expense/pending.html.twig', [
            'page_setup' => $this->createPageSetup('expense.pending_title'),
            'expenses' => $repository->findPendingForUser($this->getUser()),
        ]);
    }

    #[Route(path: '/create', name: 'expense_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_expense')]
    public function create(Request $request, ExpenseRepository $repository, ExpenseAllocationAmountUpdater $amountUpdater, AllocationPercentageValidator $percentageValidator): Response
    {
        $expense = new Expense();
        $expense->setCreatedBy($this->getUser());

        return $this->form($expense, $request, $repository, $amountUpdater, $percentageValidator, 'expense_create');
    }

    #[Route(path: '/{id}/edit', name: 'expense_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_expense', 'expense')]
    public function edit(Expense $expense, Request $request, ExpenseRepository $repository, ExpenseAllocationAmountUpdater $amountUpdater, AllocationPercentageValidator $percentageValidator): Response
    {
        return $this->form($expense, $request, $repository, $amountUpdater, $percentageValidator, 'expense_edit', ['id' => $expense->getId()]);
    }

    #[Route(path: '/{id}', name: 'expense_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('view_expense', 'expense')]
    public function view(Expense $expense): Response
    {
        $chargeForms = [];
        if ($expense->isApproved()) {
            foreach ($expense->getAllocations() as $allocation) {
                if ($allocation->isCharged()) {
                    continue;
                }
                $project = $allocation->getProject();
                if (null === $project) {
                    continue;
                }
                $form = $this->createForm(ExpenseChargeForm::class, null, [
                    'action' => $this->generateUrl('expense_allocation_charge', ['id' => $allocation->getId()]),
                    'method' => 'POST',
                    'project' => $project,
                ]);
                $allocationId = $allocation->getId();
                if (null !== $allocationId) {
                    $chargeForms[$allocationId] = $form->createView();
                }
            }
        }

        return $this->render('expense/view.html.twig', [
            'page_setup' => $this->createPageSetup('expense.view_title'),
            'expense' => $expense,
            'charge_forms' => $chargeForms,
        ]);
    }

    #[Route(path: '/{id}/submit', name: 'expense_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('edit_expense', 'expense')]
    public function submit(Expense $expense, Request $request, ExpenseApprovalService $service, AllocationPercentageValidator $percentageValidator): Response
    {
        if (!$this->isCsrfTokenValid('expense_submit_' . $expense->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $percentages = $this->allocationPercentages($expense);

        if (!$percentageValidator->isValidForSubmit($percentages)) {
            $this->flashError('action.update.error', 'expense.allocations_not_100');

            return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
        }

        try {
            $service->submit($expense);
            $this->flashSuccess('action.update.success');
        } catch (\DomainException $exception) {
            $this->flashError('action.update.error', $exception->getMessage());
        }

        return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
    }

    #[Route(path: '/{id}/approve', name: 'expense_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('approve_expense', 'expense')]
    public function approve(Expense $expense, Request $request, ExpenseApprovalService $service): Response
    {
        return $this->decide($expense, $request, $service, true);
    }

    #[Route(path: '/{id}/reject', name: 'expense_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('reject_expense', 'expense')]
    public function reject(Expense $expense, Request $request, ExpenseApprovalService $service): Response
    {
        return $this->decide($expense, $request, $service, false);
    }

    #[Route(path: '/{id}/delete', name: 'expense_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('delete_expense', 'expense')]
    public function delete(Expense $expense, Request $request, ExpenseRepository $repository): Response
    {
        if (!$this->isCsrfTokenValid('expense_delete_' . $expense->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $repository->deleteExpense($expense);
        $this->flashSuccess('action.delete.success');

        return $this->redirectToRoute('expense_list');
    }

    #[Route(path: '/allocation/{id}/charge', name: 'expense_allocation_charge', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function charge(ExpenseAllocation $allocation, Request $request, ExpenseCrossChargeService $service): Response
    {
        $expense = $allocation->getExpense();
        if (null === $expense || !$this->isGranted('charge_expense', $expense)) {
            throw $this->createAccessDeniedException('Cannot charge this allocation.');
        }

        $project = $allocation->getProject();
        if (null === $project) {
            throw $this->createAccessDeniedException('The allocation has no project.');
        }

        $form = $this->createForm(ExpenseChargeForm::class, null, [
            'action' => $this->generateUrl('expense_allocation_charge', ['id' => $allocation->getId()]),
            'method' => 'POST',
            'project' => $project,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
        }

        /** @var array{quotation?: \App\Entity\Quotation|null} $data */
        $data = $form->getData();
        $quotation = $data['quotation'] ?? null;

        if (null === $quotation) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
        }

        try {
            $service->charge($allocation, $quotation);
            $this->flashSuccess('action.update.success');
        } catch (\DomainException $exception) {
            $this->flashError('action.update.error', $exception->getMessage());
        }

        return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
    }

    /** @param array<string, int|null> $parameters */
    private function form(Expense $expense, Request $request, ExpenseRepository $repository, ExpenseAllocationAmountUpdater $amountUpdater, AllocationPercentageValidator $percentageValidator, string $route, array $parameters = []): Response
    {
        $form = $this->createForm(ExpenseForm::class, $expense, [
            'action' => $this->generateUrl($route, $parameters),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $percentages = $this->allocationPercentages($expense);

            if (!$percentageValidator->isValidForDraft($percentages)) {
                $this->flashError('action.update.error', 'expense.allocations_over_100');
            } else {
                if (!$amountUpdater->apply($expense)) {
                    $this->flashWarning('expense.fx_rate_unavailable');
                }
                $repository->saveExpense($expense);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
            }
        }

        return $this->render('expense/edit.html.twig', [
            'page_setup' => $this->createPageSetup($expense->getId() !== null ? 'expense.edit_title' : 'expense.create_title'),
            'expense' => $expense,
            'form' => $form->createView(),
        ]);
    }

    private function decide(Expense $expense, Request $request, ExpenseApprovalService $service, bool $approve): Response
    {
        $route = $approve ? 'expense_approve' : 'expense_reject';
        $form = $this->createForm(ExpenseApprovalDecisionForm::class, null, [
            'action' => $this->generateUrl($route, ['id' => $expense->getId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashError('action.update.error');

            return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
        }

        /** @var array{note?: string|null} $data */
        $data = $form->getData();
        $note = $data['note'] ?? null;

        try {
            if ($approve) {
                $service->approve($expense, $this->getUser(), $note);
            } else {
                $service->reject($expense, $this->getUser(), $note);
            }
            $this->flashSuccess('action.update.success');
        } catch (\DomainException $exception) {
            $this->flashError('action.update.error', $exception->getMessage());
        }

        return $this->redirectToRoute('expense_view', ['id' => $expense->getId()]);
    }

    /** @return string[] */
    private function allocationPercentages(Expense $expense): array
    {
        return array_map(
            static fn (ExpenseAllocation $allocation): string => $allocation->getPercentage() ?? '0',
            $expense->getAllocations()->toArray()
        );
    }

    private function createPageSetup(string $title = 'expenses'): PageSetup
    {
        return new PageSetup($title);
    }
}
