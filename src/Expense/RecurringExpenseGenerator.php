<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates one new draft {@see Expense} copy per period for every
 * monthly-recurring source (spec: "Generate monthly recurring copies").
 * Idempotent per (source, period): a pre-check here, backed by the
 * database's unique index as the final guarantee under concurrency
 * (design File Changes / D6).
 */
final class RecurringExpenseGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExpenseRepository $repository,
    ) {
    }

    /**
     * @return ExpenseRecurrenceResult[]
     */
    public function generateForPeriod(string $periodKey): array
    {
        return array_map(
            fn (Expense $source): ExpenseRecurrenceResult => $this->generateOne($source, $periodKey),
            $this->repository->findRecurringSources(),
        );
    }

    public function generateOne(Expense $source, string $periodKey): ExpenseRecurrenceResult
    {
        if (Expense::RECURRENCE_MONTH !== $source->getRecurrence()) {
            return ExpenseRecurrenceResult::skippedNotRecurring($source);
        }

        if (null !== $this->repository->findGeneratedCopy($source, $periodKey)) {
            return ExpenseRecurrenceResult::skippedExisting($source);
        }

        $copy = $this->cloneForPeriod($source, $periodKey);

        $this->entityManager->beginTransaction();

        try {
            $this->repository->saveExpense($copy);
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $exception;
        }

        return ExpenseRecurrenceResult::generated($source, $copy);
    }

    private function cloneForPeriod(Expense $source, string $periodKey): Expense
    {
        $copy = new Expense();
        $copy->setDescription($source->getDescription() ?? '');
        $copy->setAmount($source->getAmount() ?? 0);
        $copy->setCurrency($source->getCurrency());
        $copy->setExpenseDate(new \DateTimeImmutable($periodKey . '-01'));
        $copy->setRecurrence(Expense::RECURRENCE_MONTH);
        $copy->setCategory($source->getCategory());
        $copy->setCreatedBy($source->getCreatedBy());
        $copy->setSourceExpense($source);
        $copy->setPeriodKey($periodKey);

        foreach ($source->getAllocations() as $allocation) {
            $project = $allocation->getProject();
            if (null === $project) {
                continue;
            }

            $copy->addAllocation(
                (new ExpenseAllocation())
                    ->setProject($project)
                    ->setPercentage($allocation->getPercentage() ?? '0.00')
                    ->setAmountClp($allocation->getAmountClp())
            );
        }

        return $copy;
    }
}
