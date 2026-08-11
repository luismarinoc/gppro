<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Form\QuotationForm;
use App\Invoice\QuotationInvoiceService;
use App\Repository\QuotationCatalogItemRepository;
use App\Repository\QuotationRepository;
use App\Service\QuotationMailService;
use App\Utils\PageSetup;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/quotation')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('view_quotation')]
final class QuotationController extends AbstractController
{
    #[Route(path: '/', name: 'quotation_list', methods: ['GET'])]
    public function index(Request $request, QuotationRepository $repository): Response
    {
        $status = $request->query->get('status');
        $quotations = array_values(array_filter(
            $repository->findForListing(null, \is_string($status) ? $status : null),
            fn (Quotation $quotation): bool => $this->isGranted('view_quotation', $quotation)
        ));

        return $this->render('quotation/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'quotations' => $quotations,
            'customer' => null,
            'status' => $status,
        ]);
    }

    #[Route(path: '/customer/{id}', name: 'customer_quotation_list', methods: ['GET'])]
    #[IsGranted('access', 'customer')]
    public function customerHistory(Customer $customer, Request $request, QuotationRepository $repository): Response
    {
        $status = $request->query->get('status');

        return $this->render('quotation/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'quotations' => $repository->findForListing($customer, \is_string($status) ? $status : null),
            'customer' => $customer,
            'status' => $status,
        ]);
    }

    #[Route(path: '/create', name: 'quotation_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_quotation')]
    public function create(Request $request, QuotationRepository $repository, QuotationCatalogItemRepository $catalogRepository): Response
    {
        $quotation = new Quotation();
        $quotation->setCreatedBy($this->getUser());

        return $this->form($quotation, $request, $repository, $catalogRepository, 'quotation_create');
    }

    #[Route(path: '/{id}/edit', name: 'quotation_edit', methods: ['GET', 'POST'])]
    #[IsGranted('edit_quotation', 'quotation')]
    public function edit(Quotation $quotation, Request $request, QuotationRepository $repository, QuotationCatalogItemRepository $catalogRepository): Response
    {
        return $this->form($quotation, $request, $repository, $catalogRepository, 'quotation_edit', ['id' => $quotation->getId()]);
    }

    #[Route(path: '/{id}/send', name: 'quotation_send', methods: ['POST'])]
    #[IsGranted('send_quotation', 'quotation')]
    public function send(Quotation $quotation, Request $request, QuotationMailService $mailService): Response
    {
        if (!$this->isCsrfTokenValid('quotation_send_' . $quotation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $mailService->send($quotation);
        $this->flashSuccess('Quotation sent successfully.');

        return $this->redirectToRoute('quotation_view', ['id' => $quotation->getId()]);
    }

    #[Route(path: '/{id}/convert', name: 'quotation_convert', methods: ['POST'])]
    #[IsGranted('convert_quotation', 'quotation')]
    public function convert(Quotation $quotation, Request $request, QuotationInvoiceService $service, EventDispatcherInterface $dispatcher): Response
    {
        if (!$this->isCsrfTokenValid('quotation_convert_' . $quotation->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $template = $quotation->getCustomer()?->getInvoiceTemplate();
            if ($template === null) {
                throw new \DomainException('The customer has no invoice template configured.');
            }
            $service->convert($quotation, $template, $this->getUser(), $dispatcher);
            $this->flashSuccess('quotation.convert.success');
        } catch (\DomainException $exception) {
            $this->flashError('quotation.convert.error', $exception->getMessage());
        } catch (\Exception $exception) {
            $this->flashUpdateException($exception);
        }

        return $this->redirectToRoute('quotation_view', ['id' => $quotation->getId()]);
    }

    #[Route(path: '/{id}', name: 'quotation_view', methods: ['GET'])]
    #[IsGranted('view_quotation', 'quotation')]
    public function view(Quotation $quotation): Response
    {
        return $this->render('quotation/view.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'quotation' => $quotation,
        ]);
    }

    /** @param array<string, int|null> $parameters */
    private function form(Quotation $quotation, Request $request, QuotationRepository $repository, QuotationCatalogItemRepository $catalogRepository, string $route, array $parameters = []): Response
    {
        $form = $this->createForm(QuotationForm::class, $quotation, [
            'action' => $this->generateUrl($route, $parameters),
            'method' => 'POST',
            'catalog_items' => $catalogRepository->findActive(),
            'customer' => $quotation->getCustomer(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer = $quotation->getCustomer();
            $project = $quotation->getProject();
            if ($customer === null || !$this->isGranted('access', $customer)) {
                throw $this->createAccessDeniedException('The quotation customer is not accessible.');
            }
            if ($project !== null && ($project->getCustomer() !== $customer || !$this->isGranted('access', $project))) {
                throw $this->createAccessDeniedException('The quotation project does not belong to its customer.');
            }

            $repository->saveQuotation($quotation);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('quotation_view', ['id' => $quotation->getId()]);
        }

        return $this->render('quotation/edit.html.twig', [
            'page_setup' => $this->createPageSetup($quotation->getId() !== null ? 'quotation.edit_title' : 'quotation.create_title'),
            'quotation' => $quotation,
            'form' => $form->createView(),
        ]);
    }

    private function createPageSetup(string $title = 'quotations'): PageSetup
    {
        return new PageSetup($title);
    }
}
