<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\FxRate;
use App\Form\FxRateEditForm;
use App\Form\Toolbar\FxRateToolbarForm;
use App\Repository\FxRateRepository;
use App\Repository\Query\FxRateQuery;
use App\Utils\DataTable;
use App\Utils\PageSetup;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/fx-rates')]
#[IsGranted('view_fx_rate')]
final class FxRateController extends AbstractController
{
    #[Route(path: '/', defaults: ['page' => 1], name: 'fx_rates', methods: ['GET'])]
    #[Route(path: '/page/{page}', requirements: ['page' => '[1-9]\d*'], name: 'fx_rates_paginated', methods: ['GET'])]
    public function listFxRates(FxRateRepository $repository, Request $request, int $page): Response
    {
        $query = new FxRateQuery();
        $query->setPage($page);

        $form = $this->getToolbarForm($query);
        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('fx_rates');
        }

        $entries = $repository->getPagerfantaForQuery($query);

        $table = new DataTable('admin_fx_rates', $query);
        $table->setSearchForm($form);
        $table->setPagination($entries);
        $table->setPaginationRoute('fx_rates_paginated');
        $table->setReloadEvents('gppro.fxRateUpdate');

        $table->addColumn('date', ['class' => 'alwaysVisible']);
        $table->addColumn('indicator', ['class' => 'alwaysVisible']);
        $table->addColumn('rateValue', ['title' => 'value', 'class' => 'text-center w-min']);
        $table->addColumn('modifiedAt', ['title' => 'modified_at', 'class' => 'd-none text-center w-min']);
        $table->addColumn('actions', ['class' => 'actions']);

        $page = new PageSetup('fx_rates');
        $page->setActionName('fx_rates');
        $page->setDataTable($table);

        return $this->render('fx_rates/index.html.twig', [
            'page_setup' => $page,
            'dataTable' => $table,
        ]);
    }

    #[Route(path: '/create', name: 'fx_rates_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_fx_rate')]
    public function createAction(FxRateRepository $repository, Request $request): Response
    {
        $fxRate = new FxRate();

        return $this->renderEditForm($fxRate, $repository, $request, $this->generateUrl('fx_rates_create'));
    }

    #[Route(path: '/{id}/edit', name: 'fx_rates_edit', methods: ['GET', 'POST'])]
    #[IsGranted('manage_fx_rate')]
    public function editAction(FxRate $fxRate, FxRateRepository $repository, Request $request): Response
    {
        return $this->renderEditForm($fxRate, $repository, $request, $this->generateUrl('fx_rates_edit', ['id' => $fxRate->getId()]));
    }

    #[Route(path: '/{id}/delete', name: 'fx_rates_delete', methods: ['GET', 'POST'])]
    #[IsGranted('delete_fx_rate')]
    public function deleteAction(FxRate $fxRate, FxRateRepository $repository, Request $request): Response
    {
        $deleteForm = $this->createFormBuilder(null, [
                'attr' => [
                    'data-form-event' => 'gppro.fxRateDelete',
                    'data-msg-success' => 'action.delete.success',
                    'data-msg-error' => 'action.delete.error',
                ]
            ])
            ->setAction($this->generateUrl('fx_rates_delete', ['id' => $fxRate->getId()]))
            ->setMethod('POST')
            ->getForm();

        $deleteForm->handleRequest($request);

        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            try {
                $repository->deleteFxRate($fxRate);
                $this->flashSuccess('action.delete.success');
            } catch (\Exception $ex) {
                $this->flashDeleteException($ex);
            }

            return $this->redirectToRoute('fx_rates');
        }

        return $this->render('fx_rates/delete.html.twig', [
            'page_setup' => new PageSetup('fx_rates'),
            'fx_rate' => $fxRate,
            'form' => $deleteForm->createView(),
        ]);
    }

    private function renderEditForm(FxRate $fxRate, FxRateRepository $repository, Request $request, string $actionUrl): Response
    {
        $editForm = $this->createForm(FxRateEditForm::class, $fxRate, [
            'action' => $actionUrl,
            'method' => 'POST',
        ]);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $fxRate->markAsModified();
                $repository->saveFxRate($fxRate);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('fx_rates');
            } catch (\Exception $ex) {
                $this->handleFormUpdateException($ex, $editForm);
            }
        }

        return $this->render('fx_rates/edit.html.twig', [
            'page_setup' => new PageSetup('fx_rates'),
            'fx_rate' => $fxRate,
            'form' => $editForm->createView(),
        ]);
    }

    private function getToolbarForm(FxRateQuery $query): FormInterface
    {
        return $this->createSearchForm(FxRateToolbarForm::class, $query, [
            'action' => $this->generateUrl('fx_rates', [
                'page' => $query->getPage(),
            ]),
        ]);
    }
}
