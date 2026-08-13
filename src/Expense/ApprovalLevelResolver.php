<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\ExpenseApprovalLevel;
use App\Repository\ExpenseApprovalLevelRepository;

/**
 * Resolves which approval levels an expense amount must pass through. The
 * cumulative rule (spec: "Submit freezes required approval levels") is
 * domain logic, deliberately kept out of the repository (design D4).
 */
final class ApprovalLevelResolver
{
    public function __construct(private readonly ExpenseApprovalLevelRepository $repository)
    {
    }

    /**
     * @return ExpenseApprovalLevel[] level ASC, only levels whose minAmount <= $amountClp
     */
    public function resolve(int $amountClp): array
    {
        return array_values(array_filter(
            $this->repository->findAllOrdered(),
            static fn (ExpenseApprovalLevel $level): bool => $level->getMinAmount() <= $amountClp,
        ));
    }

    public function requiredLevelsFor(int $amountClp): int
    {
        return \count($this->resolve($amountClp));
    }
}
