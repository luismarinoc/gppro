<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoicePaymentApproval;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transactional submit/approve/reject for an {@see Invoice}'s payment
 * approval (design D6 data flow). Each operation writes an
 * {@see InvoicePaymentApproval} audit row and the invoice's own transition
 * in a single transaction, mirroring App\Expense\ExpenseApprovalService.
 */
final class InvoicePaymentApprovalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoicePaymentApprovalLevelResolver $levelResolver,
        private readonly InvoicePaymentApprovalPolicy $policy,
    ) {
    }

    /**
     * Computes and freezes paymentRequiredLevels for the current level
     * configuration and the invoice's current total (decision 5). Later
     * changes to the level configuration or the invoice amount must not
     * affect this invoice.
     */
    public function submit(Invoice $invoice): Invoice
    {
        $this->entityManager->beginTransaction();

        try {
            $requiredLevels = $this->levelResolver->requiredLevelsFor($invoice->getTotal());
            $invoice->submitForPaymentApproval($requiredLevels);

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $invoice;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function approve(Invoice $invoice, User $approver, ?string $note = null): Invoice
    {
        $this->entityManager->beginTransaction();

        try {
            if (!$this->policy->canApprove($invoice, $approver)) {
                throw new \DomainException('This user cannot approve this invoice payment.');
            }

            $level = $invoice->nextPendingPaymentLevel();
            if (null === $level) {
                throw new \DomainException('This invoice has no pending payment approval level.');
            }

            $approval = (new InvoicePaymentApproval())
                ->setInvoice($invoice)
                ->setLevel($level)
                ->setDecision(InvoicePaymentApproval::DECISION_APPROVED)
                ->setApprovedBy($approver)
                ->setApprovedAt(new \DateTimeImmutable())
                ->setNote($note);

            $invoice->clearPaymentLevel($level);

            $this->entityManager->persist($approval);
            $this->entityManager->persist($invoice);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $invoice;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function reject(Invoice $invoice, User $rejecter, ?string $note = null): Invoice
    {
        $this->entityManager->beginTransaction();

        try {
            if (!$this->policy->canReject($invoice, $rejecter)) {
                throw new \DomainException('This user cannot reject this invoice payment.');
            }

            $level = $invoice->nextPendingPaymentLevel();
            if (null === $level) {
                throw new \DomainException('This invoice has no pending payment approval level.');
            }

            $approval = (new InvoicePaymentApproval())
                ->setInvoice($invoice)
                ->setLevel($level)
                ->setDecision(InvoicePaymentApproval::DECISION_REJECTED)
                ->setApprovedBy($rejecter)
                ->setApprovedAt(new \DateTimeImmutable())
                ->setNote($note);

            $invoice->rejectPaymentApproval();

            $this->entityManager->persist($approval);
            $this->entityManager->persist($invoice);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $invoice;
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
