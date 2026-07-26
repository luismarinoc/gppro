<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Milestone;

use App\Entity\Customer;
use App\Form\MultiUpdate\MultiUpdateTable;
use App\Form\MultiUpdate\MultiUpdateTableDTO;
use App\Form\Type\MilestoneInvoiceTemplateType;
use App\Repository\MilestoneRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the milestone multi-select + template form that posts to
 * `milestone_invoice_create`. Shared by the dedicated milestone-invoicing
 * screen and the "milestone" mode of the main invoice screen, so both stay
 * wired to the exact same POST contract.
 */
final class MilestoneInvoiceSelectionFormFactory
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MilestoneRepository $repository,
    ) {
    }

    public function create(Customer $customer): FormInterface
    {
        $url = $this->urlGenerator->generate('milestone_invoice_create', ['id' => $customer->getId()]);

        $dto = new MultiUpdateTableDTO();
        $dto->addAction('milestone_invoice.action.generate', $url);

        return $this->formFactory->create(MultiUpdateTable::class, $dto, [
            'action' => $url,
            'repository' => $this->repository,
            'method' => 'POST',
        ])->add('template', MilestoneInvoiceTemplateType::class, [
            'mapped' => false,
        ]);
    }
}
