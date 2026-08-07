<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Quotation;
use App\Entity\QuotationEmail;
use App\Event\EmailEvent;
use App\Repository\QuotationEmailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class QuotationMailService
{
    private const TOKEN_TTL = 604800;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuotationEmailRepository $emailRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly QuotationPdfRendererInterface $pdfRenderer,
    ) {
    }

    public function send(Quotation $quotation): QuotationEmail
    {
        $customer = $quotation->getCustomer();
        $recipient = $customer?->getEmail();
        if ($recipient === null || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException('The quotation customer must have an email address.');
        }
        if ($quotation->getStatus() !== Quotation::STATUS_DRAFT) {
            throw new \DomainException('Only draft quotations can be sent.');
        }
        if ($quotation->getId() === null) {
            throw new \DomainException('The quotation must be saved before it can be sent.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $emailAudit = new QuotationEmail(
            $quotation,
            $recipient,
            hash('sha256', $rawToken),
            $now->modify('+' . self::TOKEN_TTL . ' seconds'),
            $now
        );

        $responseUrl = $this->urlGenerator->generate('quotation_public_response', ['token' => $rawToken], UrlGeneratorInterface::ABSOLUTE_URL);
        $message = (new Email())
            ->to($recipient)
            ->subject('Quotation #' . $quotation->getId())
            ->html($this->twig->render('email/quotation.html.twig', [
                'quotation' => $quotation,
                'accept_url' => $responseUrl . '?response=accepted',
                'reject_url' => $responseUrl . '?response=rejected',
            ]))
            ->text('Please review quotation #' . $quotation->getId() . "\n\nAccept: " . $responseUrl . '?response=accepted' . "\nReject: " . $responseUrl . '?response=rejected');

        $message->attach($this->pdfRenderer->render($quotation), 'quotation-' . $quotation->getId() . '.pdf', 'application/pdf');
        $this->entityManager->persist($emailAudit);
        $quotation->markAsSent();
        $this->dispatcher->dispatch(new EmailEvent($message));
        $this->entityManager->flush();

        return $emailAudit;
    }

    public function findValidResponse(string $rawToken): ?QuotationEmail
    {
        return $this->emailRepository->findValidToken(
            hash('sha256', $rawToken),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
    }

    public function respond(string $rawToken, string $response, ?string $ip, ?string $userAgent): QuotationEmail
    {
        $this->entityManager->beginTransaction();

        try {
            $emailAudit = $this->emailRepository->findValidTokenForUpdate(
                hash('sha256', $rawToken),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            if ($emailAudit === null) {
                throw new \DomainException('Invalid or expired quotation response link.');
            }

            $quotation = $emailAudit->getQuotation();
            if ($response === 'accepted') {
                $quotation->accept();
            } elseif ($response === 'rejected') {
                $quotation->reject();
            } else {
                throw new \InvalidArgumentException('Unknown quotation response.');
            }

            $emailAudit->recordResponse($response, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $ip, $userAgent);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $emailAudit;
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $exception;
        }
    }
}
