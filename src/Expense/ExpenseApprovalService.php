<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseApproval;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transactional submit/approve/reject for an {@see Expense} (spec: "Submit
 * freezes required approval levels", "Approve each level", "Reject discards
 * accumulated approvals"). Each operation writes an {@see ExpenseApproval}
 * audit row and the expense's own transition in a single transaction,
 * mirroring QuotationInvoiceService::convert().
 */
final class ExpenseApprovalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApprovalLevelResolver $levelResolver,
        private readonly ExpenseApprovalPolicy $policy,
        private readonly ExpenseClpAmountResolver $clpAmountResolver,
        private readonly ExpenseAllocationAmountUpdater $allocationAmountUpdater,
    ) {
    }

    /**
     * Converts the expense amount to CLP once (design D4), then freezes
     * BOTH the allocation split and requiredLevels from that same
     * converted amount for the current level configuration. Later changes
     * to the level configuration, or to the FX rate, must not affect this
     * expense. Blocked with a DomainException before any write when no FX
     * rate is available for the expense's currency/date (design D3).
     */
    public function submit(Expense $expense): Expense
    {
        $this->entityManager->beginTransaction();

        try {
            $clpAmount = $this->clpAmountResolver->toClp($expense);

            if (null === $clpAmount) {
                throw new \DomainException('No exchange rate is available to convert this expense to CLP.');
            }

            $this->allocationAmountUpdater->applyAmount($expense, $clpAmount);

            $requiredLevels = $this->levelResolver->requiredLevelsFor($clpAmount);
            $expense->submitForApproval($requiredLevels);

            $this->entityManager->persist($expense);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $expense;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function approve(Expense $expense, User $approver, ?string $note = null): Expense
    {
        $this->entityManager->beginTransaction();

        try {
            if (!$this->policy->canApprove($expense, $approver)) {
                throw new \DomainException('This user cannot approve this expense.');
            }

            $level = $expense->nextPendingLevel();
            if (null === $level) {
                throw new \DomainException('This expense has no pending level to approve.');
            }

            $approval = (new ExpenseApproval())
                ->setExpense($expense)
                ->setLevel($level)
                ->setApprovalAttempt($expense->getApprovalAttempt())
                ->setDecision(ExpenseApproval::DECISION_APPROVED)
                ->setApprovedBy($approver)
                ->setApprovedAt(new \DateTimeImmutable())
                ->setNote($note);

            $expense->clearLevel($level);

            $this->entityManager->persist($approval);
            $this->entityManager->persist($expense);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $expense;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function reject(Expense $expense, User $rejecter, ?string $note = null): Expense
    {
        $this->entityManager->beginTransaction();

        try {
            if (!$this->policy->canReject($expense, $rejecter)) {
                throw new \DomainException('This user cannot reject this expense.');
            }

            $level = $expense->nextPendingLevel();
            if (null === $level) {
                throw new \DomainException('This expense has no pending level to reject.');
            }

            $approval = (new ExpenseApproval())
                ->setExpense($expense)
                ->setLevel($level)
                ->setApprovalAttempt($expense->getApprovalAttempt())
                ->setDecision(ExpenseApproval::DECISION_REJECTED)
                ->setApprovedBy($rejecter)
                ->setApprovedAt(new \DateTimeImmutable())
                ->setNote($note);

            $expense->rejectApproval();

            $this->entityManager->persist($approval);
            $this->entityManager->persist($expense);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $expense;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    private function rollback(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
    }
}
