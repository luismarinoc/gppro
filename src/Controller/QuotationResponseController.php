<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Service\QuotationMailService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuotationResponseController extends AbstractController
{
    #[Route(path: '/quotation/respond/{token}', name: 'quotation_public_response', methods: ['GET', 'POST'], requirements: ['token' => '[A-Za-z0-9_-]+'])]
    public function respond(string $token, Request $request, QuotationMailService $service): Response
    {
        $audit = $service->findValidResponse($token);
        if ($audit === null) {
            throw $this->createNotFoundException('Invalid or expired quotation response link.');
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            $response = $request->request->getString('response');
            if (!\in_array($response, ['accepted', 'rejected'], true)) {
                throw $this->createNotFoundException('Invalid quotation response.');
            }

            try {
                $service->respond($token, $response, $request->getClientIp(), $request->headers->get('User-Agent'));
            } catch (\DomainException) {
                throw $this->createNotFoundException('Invalid or expired quotation response link.');
            }

            return $this->render('quotation/public_response.html.twig', [
                'quotation' => $audit->getQuotation(),
                'response' => $response,
            ]);
        }

        return $this->render('quotation/public_response_confirm.html.twig', [
            'quotation' => $audit->getQuotation(),
            'token' => $token,
            'response' => $request->query->getString('response'),
        ]);
    }
}
