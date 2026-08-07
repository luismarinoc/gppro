<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationEmail;
use App\Repository\QuotationEmailRepository;
use App\Service\QuotationMailService;
use App\Service\QuotationPdfRendererInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[CoversClass(QuotationMailService::class)]
class QuotationMailServiceTest extends TestCase
{
    public function testSendingRequiresCustomerEmail(): void
    {
        $service = new QuotationMailService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(QuotationEmailRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(QuotationPdfRendererInterface::class)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('must have an email address');
        $service->send((new Quotation())->setCustomer(new Customer('No email')));
    }

    public function testSendingDoesNotAllowTerminalQuotation(): void
    {
        $customer = new Customer('Accepted');
        $customer->setEmail('client@example.com');
        $quotation = (new Quotation())->setCustomer($customer);
        $quotation->markAsSent()->accept();
        $service = new QuotationMailService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(QuotationEmailRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(QuotationPdfRendererInterface::class)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only draft quotations can be sent');
        $service->send($quotation);
    }

    public function testInvalidOrExpiredTokenCannotRespond(): void
    {
        $repository = $this->createMock(QuotationEmailRepository::class);
        $repository->expects($this->once())->method('findValidTokenForUpdate')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $entityManager->method('getConnection')->willReturn($connection);
        $service = new QuotationMailService(
            $entityManager,
            $repository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(QuotationPdfRendererInterface::class)
        );

        $this->expectException(\DomainException::class);
        $service->respond('not-a-valid-token', 'accepted', null, null);
    }

    public function testResponseIsBoundToTheQuotationStoredWithTheToken(): void
    {
        $quotation = (new Quotation())->setCustomer(new Customer('Token customer'));
        $quotation->markAsSent();
        $audit = new QuotationEmail($quotation, 'client@example.com', str_repeat('a', 64), new \DateTimeImmutable('+1 day'), new \DateTimeImmutable());
        $repository = $this->createMock(QuotationEmailRepository::class);
        $repository->method('findValidTokenForUpdate')->willReturn($audit);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())->method('flush');
        $service = new QuotationMailService(
            $entityManager,
            $repository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Environment::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(QuotationPdfRendererInterface::class)
        );

        $service->respond('opaque-token', 'accepted', '192.0.2.1', 'test-agent');

        self::assertSame(Quotation::STATUS_ACCEPTED, $quotation->getStatus());
        self::assertSame($quotation, $audit->getQuotation());
    }

    public function testSendingAttachesQuotationPdfBeforeMarkingItSent(): void
    {
        $customer = new Customer('PDF customer');
        $customer->setEmail('client@example.com');
        $quotation = (new Quotation())->setCustomer($customer);
        $id = new \ReflectionProperty(Quotation::class, 'id');
        $id->setValue($quotation, 42);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<p>quotation</p>');
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://example.com/quotation-response/token');
        $pdf = $this->createMock(QuotationPdfRendererInterface::class);
        $pdf->expects(self::once())->method('render')->with($quotation)->willReturn('%PDF');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(static function ($event): bool {
            $email = $event->getEmail();

            return $email->getAttachments()[0]->getFilename() === 'quotation-42.pdf';
        }));

        $service = new QuotationMailService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(QuotationEmailRepository::class),
            $dispatcher,
            $twig,
            $urls,
            $pdf
        );

        $service->send($quotation);

        self::assertSame(Quotation::STATUS_SENT, $quotation->getStatus());
    }
}
