<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\InvoiceTemplate;
use App\Entity\Milestone;
use App\Form\MultiUpdate\MultiUpdateTableDTO;
use App\Invoice\InvoiceService;
use App\Invoice\MilestoneInvoiceService;
use App\Milestone\InvoiceableMilestoneFinder;
use App\Milestone\MilestoneInvoiceSelectionFormFactory;
use App\Repository\MilestoneRepository;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\MilestoneInvoiceQuery;
use App\Utils\DataTable;
use App\Utils\PageSetup;
use App\Utils\Pagination;
use Exception;
use Pagerfanta\Adapter\ArrayAdapter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to generate an invoice from one or more fixed-price
 * project milestones of a single customer (design D6/D7).
 *
 * Reuses the existing create_invoice/view_invoice permissions — no new
 * permission was introduced for this feature.
 */
#[Route(path: '/invoice/milestones')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('view_invoice')]
final class MilestoneInvoiceController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * Entry point reachable from the main navigation menu (no customer in the
     * URL yet): lists the customers that currently have at least one
     * invoiceable milestone, so the user can pick which one to invoice.
     * Access-scoped per customer, same as every other listing in this
     * controller — a teamlead only sees customers they can access.
     */
    #[Route(path: '/', name: 'milestone_invoice_customers', methods: ['GET'])]
    #[IsGranted('create_invoice')]
    public function pickCustomerAction(MilestoneRepository $milestoneRepository): Response
    {
        $customers = array_values(array_filter(
            $milestoneRepository->findCustomersWithInvoiceableMilestones(),
            fn (Customer $customer): bool => $this->isGranted('access', $customer)
        ));

        $page = new PageSetup('milestone_invoice.title');
        $page->setActionName('milestone_invoice');

        return $this->render('milestone-invoice/customers.html.twig', [
            'page_setup' => $page,
            'customers' => $customers,
        ]);
    }

    #[Route(path: '/{id}', name: 'milestone_invoice_index', methods: ['GET'])]
    #[IsGranted('create_invoice')]
    #[IsGranted('access', 'customer')]
    public function indexAction(Customer $customer, InvoiceableMilestoneFinder $finder, MilestoneInvoiceSelectionFormFactory $formFactory): Response
    {
        $milestones = $finder->findForCustomer($customer);

        $form = $formFactory->create($customer);

        $table = new DataTable('milestone_invoice', new BaseQuery());
        $table->setPagination(new Pagination(new ArrayAdapter($milestones)));
        $table->setBatchForm($form);
        $table->setReloadEvents('gppro.invoiceUpdate');

        $table->addColumn('id', ['class' => 'alwaysVisible multiCheckbox', 'orderBy' => false, 'title' => false, 'batchUpdate' => true]);
        $table->addColumn('name', ['class' => 'alwaysVisible']);
        $table->addColumn('project', ['class' => 'd-none d-sm-table-cell', 'orderBy' => false]);
        $table->addColumn('due_date', ['class' => 'd-none w-min', 'orderBy' => false]);
        $table->addColumn('value', ['class' => 'text-end w-min', 'orderBy' => false]);
        $table->addColumn('currency', ['class' => 'd-none text-end w-min', 'orderBy' => false]);

        $page = new PageSetup('milestone_invoice.title');
        $page->setDataTable($table);
        $page->setActionName('milestone_invoice');

        return $this->render('milestone-invoice/index.html.twig', [
            'page_setup' => $page,
            'dataTable' => $table,
            'customer' => $customer,
        ]);
    }

    #[Route(path: '/{id}/create', name: 'milestone_invoice_create', methods: ['POST'])]
    #[IsGranted('create_invoice')]
    #[IsGranted('access', 'customer')]
    public function createAction(Customer $customer, Request $request, InvoiceableMilestoneFinder $finder, MilestoneInvoiceSelectionFormFactory $formFactory, MilestoneInvoiceService $milestoneInvoiceService, InvoiceService $invoiceService): Response
    {
        $form = $formFactory->create($customer);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flashFormError($form);

            return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
        }

        /** @var MultiUpdateTableDTO $dto */
        $dto = $form->getData();

        $templateData = $form->get('template')->getData();
        $template = $templateData instanceof InvoiceTemplate ? $templateData : null;

        $milestones = [];
        foreach ($dto->getEntities() as $entity) {
            if ($entity instanceof Milestone) {
                $milestones[] = $entity;
            }
        }

        if ([] === $milestones || null === $template) {
            $this->flashError('milestone_invoice.error.empty_selection');

            return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
        }

        // Reload-and-revalidate server-side (design D6): the CSV of ids is
        // untrusted client input. Never trust it to already be scoped to a
        // single customer, even though the listing that generated it was.
        $customerIds = [];
        foreach ($milestones as $milestone) {
            $milestoneCustomer = $milestone->getProject()?->getCustomer();
            $customerIds[$milestoneCustomer?->getId() ?? 0] = true;
        }

        if (\count($customerIds) > 1) {
            $this->flashError('milestone_invoice.error.mixed_customer');

            return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
        }

        // Re-assert at generation time (design D6 / FX-Convertibility Gate
        // spec scenario): the selection may have gone stale between listing
        // and submit — another request may have invoiced one of these
        // milestones meanwhile, or its FX rate may no longer be available.
        // Fail the whole request with a flash, never a partial invoice.
        foreach ($milestones as $milestone) {
            if ($milestone->isInvoiced() || !$finder->isConvertible($milestone)) {
                $this->flashError('milestone_invoice.error.stale_selection');

                return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
            }
        }

        $resolvedCustomer = $milestones[0]->getProject()?->getCustomer();

        if (null === $resolvedCustomer) {
            $this->flashError('milestone_invoice.error.empty_selection');

            return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
        }

        // IDOR defense-in-depth (same class of check as the mixed-customer
        // validation above, but catching a DIFFERENT gap): the mixed-customer
        // check only rejects a selection spanning MULTIPLE customers among
        // themselves. It does NOT catch a single, internally-consistent
        // selection that belongs entirely to a customer other than the one
        // already access-checked by the route's #[IsGranted('access',
        // 'customer')] above. Never trust that the resolved customer matches
        // the route customer just because `access` passed for the route —
        // `access` was only ever evaluated against $customer, never against
        // $resolvedCustomer.
        if ($resolvedCustomer->getId() !== $customer->getId()) {
            $this->flashError('milestone_invoice.error.unauthorized_customer');

            return $this->redirectToRoute('milestone_invoice_index', ['id' => $customer->getId()]);
        }

        try {
            $invoiceService->createInvoice(
                $milestoneInvoiceService->createModel(
                    $this->buildMilestoneInvoiceQuery($resolvedCustomer, $milestones, $template)
                ),
                $this->dispatcher
            );

            $this->flashSuccess('action.update.success');
        } catch (Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('milestone_invoice_index', ['id' => $resolvedCustomer->getId()]);
    }

    /**
     * @param Milestone[] $milestones
     */
    private function buildMilestoneInvoiceQuery(Customer $customer, array $milestones, InvoiceTemplate $template): MilestoneInvoiceQuery
    {
        $query = new MilestoneInvoiceQuery();
        $query->setCustomers([$customer]);
        $query->setMilestones($milestones);
        $query->setTemplate($template);
        // never let the customer's saved default template silently override
        // the calculator-filtered template picked in this flow
        $query->setAllowTemplateOverwrite(false);
        $query->setInvoiceDate($this->getDateTimeFactory()->createDateTime());
        $query->setCurrentUser($this->getUser());

        return $query;
    }

    private function flashFormError(FormInterface $form): void
    {
        $err = '';
        foreach ($form->getErrors(true, true) as $error) {
            $err .= PHP_EOL . '[' . $error->getOrigin()?->getName() . '] ' . $error->getMessage();
        }

        $this->flashError('action.update.error', $err);
    }
}
