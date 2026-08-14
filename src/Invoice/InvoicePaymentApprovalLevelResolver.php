<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\InvoicePaymentApprovalLevel;
use App\Repository\InvoicePaymentApprovalLevelRepository;

/**
 * Resolves which payment-approval levels an invoice amount must pass
 * through. Duplicates App\Expense\ApprovalLevelResolver's ~15-line algorithm
 * (design D1) rather than reusing it, because ApprovalLevelResolver's
 * constructor is hard-typed to ExpenseApprovalLevelRepository and touching
 * that shipped class to extract a shared interface is out of scope.
 */
final class InvoicePaymentApprovalLevelResolver
{
    public function __construct(private readonly InvoicePaymentApprovalLevelRepository $repository)
    {
    }

    /**
     * @return InvoicePaymentApprovalLevel[] level ASC, only levels whose minAmount <= $amount
     */
    public function resolve(float $amount): array
    {
        return array_values(array_filter(
            $this->repository->findAllOrdered(),
            static fn (InvoicePaymentApprovalLevel $level): bool => $level->getMinAmount() <= $amount,
        ));
    }

    public function requiredLevelsFor(float $amount): int
    {
        return \count($this->resolve($amount));
    }
}
