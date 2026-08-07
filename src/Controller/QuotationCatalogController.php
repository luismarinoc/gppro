<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\QuotationCatalogItem;
use App\Form\QuotationCatalogItemForm;
use App\Repository\QuotationCatalogItemRepository;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/quotation/catalog')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('manage_quotation_catalog')]
final class QuotationCatalogController extends AbstractController
{
    #[Route(path: '/', name: 'admin_quotation_catalog', methods: ['GET'])]
    public function index(QuotationCatalogItemRepository $repository): Response
    {
        return $this->render('quotation_catalog/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'items' => $repository->findAllOrdered(),
        ]);
    }

    #[Route(path: '/create', name: 'admin_quotation_catalog_create', methods: ['GET', 'POST'])]
    public function create(Request $request, QuotationCatalogItemRepository $repository): Response
    {
        $item = new QuotationCatalogItem();
        $item->setUnit('unit')->setDefaultPrice('0');

        return $this->form($item, $request, $repository, 'admin_quotation_catalog_create');
    }

    #[Route(path: '/{id}/edit', name: 'admin_quotation_catalog_edit', methods: ['GET', 'POST'])]
    public function edit(QuotationCatalogItem $item, Request $request, QuotationCatalogItemRepository $repository): Response
    {
        return $this->form($item, $request, $repository, 'admin_quotation_catalog_edit', ['id' => $item->getId()]);
    }

    #[Route(path: '/{id}/delete', name: 'admin_quotation_catalog_delete', methods: ['POST'])]
    public function delete(QuotationCatalogItem $item, Request $request, QuotationCatalogItemRepository $repository): Response
    {
        if (!$this->isCsrfTokenValid('quotation_catalog_item', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $repository->deleteCatalogItem($item);
        $this->flashSuccess('action.delete.success');

        return $this->redirectToRoute('admin_quotation_catalog');
    }

    /** @param array<string, int|null> $routeParameters */
    private function form(QuotationCatalogItem $item, Request $request, QuotationCatalogItemRepository $repository, string $route, array $routeParameters = []): Response
    {
        $form = $this->createForm(QuotationCatalogItemForm::class, $item, [
            'action' => $this->generateUrl($route, $routeParameters),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repository->saveCatalogItem($item);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('admin_quotation_catalog');
        }

        return $this->render('quotation_catalog/edit.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'item' => $item,
            'form' => $form->createView(),
        ]);
    }

    private function createPageSetup(): PageSetup
    {
        return new PageSetup('quotation_catalog');
    }
}
