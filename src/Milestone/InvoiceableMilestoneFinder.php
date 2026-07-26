<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Milestone;

use App\Entity\Customer;
use App\Entity\Milestone;
use App\FxRate\ClpConverter;
use App\Repository\MilestoneRepository;

/**
 * Shared logic for "which milestones of this customer can be invoiced right
 * now" — not yet invoiced, carrying a value/currency, AND actually
 * convertible to CLP today. Used by both the dedicated milestone-invoicing
 * screen and the "milestone" mode of the main invoice screen, so the two
 * never drift on what counts as invoiceable.
 */
final class InvoiceableMilestoneFinder
{
    public function __construct(
        private readonly MilestoneRepository $repository,
        private readonly ClpConverter $converter,
    ) {
    }

    /**
     * @return Milestone[]
     */
    public function findForCustomer(Customer $customer): array
    {
        $milestones = [];

        foreach ($this->repository->findInvoiceableByCustomer($customer) as $milestone) {
            if ($this->isConvertible($milestone)) {
                $milestones[] = $milestone;
            }
        }

        return $milestones;
    }

    public function isConvertible(Milestone $milestone): bool
    {
        $value = $milestone->getValue();
        $currency = $milestone->getCurrency();

        if (null === $value || null === $currency) {
            return false;
        }

        $date = $milestone->getDueDate() ?? $milestone->getCreatedAt();

        return null !== $this->converter->convert($value, $currency, $date);
    }
}
