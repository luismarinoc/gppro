<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\InvoicePaymentApprovalLevel;
use App\Form\InvoicePaymentApprovalLevelForm;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/invoice/payment-approval-levels')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[IsGranted('manage_invoice_payment_approval_levels')]
final class InvoicePaymentApprovalLevelController extends AbstractController
{
    #[Route(path: '/', name: 'admin_invoice_payment_approval_level_list', methods: ['GET'])]
    public function index(InvoicePaymentApprovalLevelRepository $repository): Response
    {
        return $this->render('invoice_payment_approval_level/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'levels' => $repository->findAllOrdered(),
        ]);
    }

    #[Route(path: '/create', name: 'admin_invoice_payment_approval_level_create', methods: ['GET', 'POST'])]
    public function create(Request $request, InvoicePaymentApprovalLevelRepository $repository): Response
    {
        $level = new InvoicePaymentApprovalLevel();

        return $this->form($level, $request, $repository, 'admin_invoice_payment_approval_level_create');
    }

    #[Route(path: '/{id}/edit', name: 'admin_invoice_payment_approval_level_edit', methods: ['GET', 'POST'])]
    public function edit(InvoicePaymentApprovalLevel $level, Request $request, InvoicePaymentApprovalLevelRepository $repository): Response
    {
        return $this->form($level, $request, $repository, 'admin_invoice_payment_approval_level_edit', ['id' => $level->getId()]);
    }

    #[Route(path: '/{id}/delete', name: 'admin_invoice_payment_approval_level_delete', methods: ['POST'])]
    public function delete(InvoicePaymentApprovalLevel $level, Request $request, InvoicePaymentApprovalLevelRepository $repository): Response
    {
        if (!$this->isCsrfTokenValid('invoice_payment_approval_level', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $repository->deleteLevel($level);
            $this->flashSuccess('action.delete.success');
        } catch (\DomainException $exception) {
            $this->flashError('action.delete.error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_invoice_payment_approval_level_list');
    }

    /** @param array<string, int|null> $routeParameters */
    private function form(InvoicePaymentApprovalLevel $level, Request $request, InvoicePaymentApprovalLevelRepository $repository, string $route, array $routeParameters = []): Response
    {
        $form = $this->createForm(InvoicePaymentApprovalLevelForm::class, $level, [
            'action' => $this->generateUrl($route, $routeParameters),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isMonotonic($level, $repository)) {
                $this->flashError('action.update.error', 'invoice_payment_approval_level.not_monotonic');
            } else {
                $repository->saveLevel($level);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('admin_invoice_payment_approval_level_list');
            }
        }

        return $this->render('invoice_payment_approval_level/edit.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'level' => $level,
            'form' => $form->createView(),
        ]);
    }

    /**
     * The cross-row strictly-increasing invariant needs sibling rows, so it is
     * enforced here rather than on the entity (mirrors the docblock on
     * ExpenseApprovalLevelController::isMonotonic()).
     */
    private function isMonotonic(InvoicePaymentApprovalLevel $level, InvoicePaymentApprovalLevelRepository $repository): bool
    {
        foreach ($repository->findAllOrdered() as $existing) {
            if ($existing->getId() === $level->getId()) {
                continue;
            }
            if ($existing->getLevel() < $level->getLevel() && $existing->getMinAmount() >= $level->getMinAmount()) {
                return false;
            }
            if ($existing->getLevel() > $level->getLevel() && $existing->getMinAmount() <= $level->getMinAmount()) {
                return false;
            }
        }

        return true;
    }

    private function createPageSetup(): PageSetup
    {
        return new PageSetup('invoice_payment_approval_level');
    }
}
