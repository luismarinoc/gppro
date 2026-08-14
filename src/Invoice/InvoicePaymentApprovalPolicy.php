<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\User;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Repository\InvoicePaymentApprovalRepository;

/**
 * Four-eyes rule for clearing/rejecting an invoice's current pending payment
 * approval level. Mirrors App\Expense\ExpenseApprovalPolicy (design D6 data
 * flow); Invoice::getUser() is the closest equivalent to Expense::getCreatedBy()
 * (the user the invoice-generation query ran under, see InvoiceService::
 * createModelWithoutEntries()).
 */
final class InvoicePaymentApprovalPolicy
{
    public function __construct(
        private readonly InvoicePaymentApprovalLevelRepository $levelRepository,
        private readonly InvoicePaymentApprovalRepository $approvalRepository,
    ) {
    }

    public function canApprove(Invoice $invoice, User $approver): bool
    {
        return $this->canDecide($invoice, $approver);
    }

    public function canReject(Invoice $invoice, User $rejecter): bool
    {
        return $this->canDecide($invoice, $rejecter);
    }

    private function canDecide(Invoice $invoice, User $user): bool
    {
        $pendingLevel = $invoice->nextPendingPaymentLevel();

        if (null === $pendingLevel) {
            return false;
        }

        if ($invoice->getUser() === $user) {
            return false;
        }

        if ($this->approvalRepository->hasUserApprovedAnyLevel($invoice, $user)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $level = $this->findLevel($pendingLevel);

        if (null === $level) {
            return false;
        }

        // OR-semantics (mirrors ExpenseApprovalPolicy): the named approver is
        // additive to the required role and sits BELOW the creator /
        // already-approved gates, so it can never bypass four-eyes.
        if ($level->getApproverUser() === $user) {
            return true;
        }

        return $user->hasRole($level->getRequiredRole());
    }

    private function findLevel(int $levelNumber): ?InvoicePaymentApprovalLevel
    {
        foreach ($this->levelRepository->findAllOrdered() as $level) {
            if ($level->getLevel() === $levelNumber) {
                return $level;
            }
        }

        return null;
    }
}
