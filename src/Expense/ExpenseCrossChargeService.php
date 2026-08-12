<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\ExpenseAllocation;
use App\Entity\Quotation;
use App\Entity\QuotationLine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Manually cross-charges an approved {@see ExpenseAllocation} onto a draft
 * CLP {@see Quotation} of the same project (spec: "Cross-charge an approved
 * allocation", design D5). No quotation logic is duplicated - this creates
 * a plain {@see QuotationLine} on the existing aggregate.
 */
final class ExpenseCrossChargeService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function charge(ExpenseAllocation $allocation, Quotation $quotation): QuotationLine
    {
        $this->entityManager->beginTransaction();

        try {
            $expense = $allocation->getExpense();
            if (null === $expense || !$expense->isApproved()) {
                throw new \DomainException('Only allocations of a fully approved expense can be cross-charged.');
            }

            if ($allocation->isCharged()) {
                throw new \DomainException('This allocation has already been charged.');
            }

            if ($quotation->getProject() === null || $quotation->getProject() !== $allocation->getProject()) {
                throw new \DomainException('The quotation must belong to the same project as the allocation.');
            }

            if (Quotation::STATUS_DRAFT !== $quotation->getStatus()) {
                throw new \DomainException('Only a draft quotation can be cross-charged.');
            }

            if (Quotation::CURRENCY_CLP !== $quotation->getCurrency()) {
                throw new \DomainException('Only a CLP quotation can be cross-charged.');
            }

            $amountClp = $allocation->getAmountClp() ?? 0;
            $description = \sprintf('%s (%s)', $expense->getDescription(), $expense->getExpenseDate()?->format('Y-m-d'));

            $line = (new QuotationLine())
                ->setDescription($description)
                ->setUnitPrice(number_format($amountClp, 4, '.', ''))
                ->setQuantity('1');

            $quotation->addLine($line);
            $allocation->markCharged($line);

            $this->entityManager->persist($quotation);
            $this->entityManager->persist($allocation);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $line;
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $exception;
        }
    }
}
