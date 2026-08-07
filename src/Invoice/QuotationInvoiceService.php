<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceTemplate;
use App\Entity\Quotation;
use App\Entity\User;
use App\Repository\Query\QuotationInvoiceQuery;
use App\Repository\QuotationInvoiceItemRepository;
use App\Repository\QuotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class QuotationInvoiceService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly QuotationRepository $quotationRepository,
        private readonly QuotationInvoiceItemRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function convert(Quotation $quotation, InvoiceTemplate $template, User $user, EventDispatcherInterface $dispatcher): Invoice
    {
        $this->entityManager->beginTransaction();

        try {
            $locked = $this->quotationRepository->lockForInvoice($quotation);
            if ($locked === null || $locked->getStatus() !== Quotation::STATUS_ACCEPTED) {
                throw new \DomainException('Only accepted quotations can be converted, and each quotation can be invoiced once.');
            }

            $query = new QuotationInvoiceQuery();
            $query->setQuotation($locked);
            $query->setTemplate($template);
            $query->setAllowTemplateOverwrite(false);
            $query->setInvoiceDate(new \DateTime());
            $query->setCurrentUser($user);

            $model = $this->invoiceService->createModelWithoutEntries($query);
            $model->addEntries($this->itemRepository->getInvoiceItemsForQuery($query));
            $calculator = $model->getCalculator();
            if ($calculator === null) {
                throw new \DomainException('The quotation invoice calculator is unavailable.');
            }
            $quotationCalculator = new QuotationInvoiceCalculator($calculator, $locked);
            $pricing = new QuotationPricing($locked);
            $baseSubtotal = $calculator->getSubtotal();
            $model->setOption('quotation.discount', $pricing->discountAmount($baseSubtotal));
            $model->setOption('quotation.surcharge', $pricing->surchargeAmount($baseSubtotal));
            $model->setOption('quotation.tax', $pricing->customTaxAmount($quotationCalculator->getSubtotal()) ?? 0.0);
            $model->setCalculator($quotationCalculator);
            $model->setUser($user);

            $invoice = $this->invoiceService->createInvoice($model, $dispatcher);
            $locked->markAsInvoiced($invoice);
            $this->entityManager->persist($locked);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $invoice;
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $exception;
        }
    }
}
