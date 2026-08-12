<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseApprovalLevel;
use App\Entity\User;
use App\Repository\ExpenseApprovalLevelRepository;
use App\Repository\ExpenseApprovalRepository;

/**
 * Four-eyes rule for clearing/rejecting an expense's current pending level
 * (spec: "Approve each level"). Kept out of the voter (design D2) so it is
 * unit-testable and reusable from Twig without pulling in the security
 * component's permission plumbing.
 */
final class ExpenseApprovalPolicy
{
    public function __construct(
        private readonly ExpenseApprovalLevelRepository $levelRepository,
        private readonly ExpenseApprovalRepository $approvalRepository,
    ) {
    }

    public function canApprove(Expense $expense, User $approver): bool
    {
        return $this->canDecide($expense, $approver);
    }

    /**
     * Rejecting the pending level is governed by the same four-eyes rule as
     * approving it (spec: "Approve each level" / "Reject discards
     * accumulated approvals" - both act on the same pending level).
     */
    public function canReject(Expense $expense, User $rejecter): bool
    {
        return $this->canDecide($expense, $rejecter);
    }

    private function canDecide(Expense $expense, User $user): bool
    {
        $pendingLevel = $expense->nextPendingLevel();

        if (null === $pendingLevel) {
            return false;
        }

        if ($expense->getCreatedBy() === $user) {
            return false;
        }

        if ($this->approvalRepository->hasUserApprovedAnyLevel($expense, $user)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $level = $this->findLevel($pendingLevel);

        return null !== $level && $user->hasRole($level->getRequiredRole());
    }

    private function findLevel(int $levelNumber): ?ExpenseApprovalLevel
    {
        foreach ($this->levelRepository->findAllOrdered() as $level) {
            if ($level->getLevel() === $levelNumber) {
                return $level;
            }
        }

        return null;
    }
}
