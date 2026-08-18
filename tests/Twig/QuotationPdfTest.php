<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Twig;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationLine;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Invoice\QuotationClpSummary;
use App\Invoice\QuotationSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

#[Group('integration')]
class QuotationPdfTest extends KernelTestCase
{
    private function renderQuotation(Quotation $quotation, string $locale = 'en'): string
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');
        /** @var RequestStack $stack */
        $stack = self::getContainer()->get('request_stack');
        $request = new Request();
        $request->setLocale($locale);
        $stack->push($request);
        self::getContainer()->get('translator')->setLocale($locale);

        $summary = QuotationSummary::fromQuotation($quotation);
        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(ClpConversion::identity('228.0000'));
        $clpSummary = QuotationClpSummary::fromSummary($summary, $converter, $quotation->getCurrency(), $quotation->getValidUntil());

        return $twig->render('quotation/pdf.html.twig', [
            'quotation' => $quotation,
            'clpSummary' => $clpSummary,
            'defaultLogo' => null,
        ]);
    }

    private function buildQuotation(Customer $customer): Quotation
    {
        $quotation = (new Quotation())->setCustomer($customer)->setCurrency(Quotation::CURRENCY_CLP);
        $quotation->addLine((new QuotationLine())->setDescription('Service')->setQuantity('1')->setUnitPrice('100'));
        $id = new \ReflectionProperty(Quotation::class, 'id');
        $id->setValue($quotation, 42);

        return $quotation;
    }

    public function testFullyPopulatedCustomerRendersIssuerAndAllReceiverFields(): void
    {
        $customer = new Customer('Acme Corp');
        $customer->setVatId('76.123.456-7');
        $customer->setAddress('Av. Providencia 1234');
        $customer->setPhone('+56 2 22334455');
        $customer->setEmail('billing@acme.cl');

        $content = $this->renderQuotation($this->buildQuotation($customer));

        // Issuer block: Gpartner Consulting DI defaults
        self::assertStringContainsString('77.073.462-2', $content);
        self::assertStringContainsString('Avenida Apoquindo 4700, Depto. 11, Las Condes, Santiago', $content);
        self::assertStringContainsString('+56 9 44516977', $content);
        self::assertStringContainsString('info@gpartnerc.com', $content);
        self::assertStringContainsString('www.gpartnerc.com', $content);

        // Receiver block: all 5 customer fields
        self::assertStringContainsString('Acme Corp', $content);
        self::assertStringContainsString('76.123.456-7', $content);
        self::assertStringContainsString('Av. Providencia 1234', $content);
        self::assertStringContainsString('+56 2 22334455', $content);
        self::assertStringContainsString('billing@acme.cl', $content);
    }

    public function testPartiallyPopulatedCustomerOmitsMissingReceiverFields(): void
    {
        $customer = new Customer('Bare Customer');
        $customer->setEmail('bare@example.com');

        $content = $this->renderQuotation($this->buildQuotation($customer));

        self::assertStringContainsString('Bare Customer', $content);
        self::assertStringContainsString('bare@example.com', $content);

        // No orphan RUT/address/phone label for a customer without those fields
        self::assertDoesNotMatchRegularExpression('/RUT[^a-zA-Z0-9]*:\s*<\/p>/', $content);
        self::assertStringNotContainsString('76.123.456-7', $content);
        self::assertStringNotContainsString('Av. Providencia 1234', $content);
        self::assertStringNotContainsString('+56 2 22334455', $content);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function statusProvider(): array
    {
        return [
            'draft' => ['draft'],
            'accepted' => ['accepted'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testSignatureSectionRendersRegardlessOfQuotationStatus(string $status): void
    {
        $customer = new Customer('Signature Customer');
        $quotation = $this->buildQuotation($customer);
        if ($status === 'accepted') {
            $quotation->markAsSent()->accept();
        }

        $content = $this->renderQuotation($quotation);

        self::assertStringContainsString('doc-signatures', $content);
        self::assertStringContainsString('Gpartner Consulting', $content);
        self::assertStringContainsString('Signature Customer', $content);
        // date lines: one per signature column
        self::assertSame(2, substr_count($content, 'quotation.signature_date'), 'Expected the date placeholder to appear once per signature column.');
    }

    public function testCustomerSignatureNamePrefillsFromContactWhenAvailable(): void
    {
        $customer = new Customer('Contact Co');
        $customer->setContact('Jane Doe');

        $content = $this->renderQuotation($this->buildQuotation($customer));

        self::assertStringContainsString('Jane Doe', $content);
    }

    public function testCustomerSignatureNameStaysBlankWithoutAContact(): void
    {
        $customer = new Customer('No Contact Co');

        $content = $this->renderQuotation($this->buildQuotation($customer));

        self::assertStringNotContainsString('Jane Doe', $content);
        self::assertMatchesRegularExpression('/sign-name">&nbsp;<\/td>/', $content);
    }

    public function testEveryNewLabelRendersInSpanishWithNoRawTranslationKey(): void
    {
        $customer = new Customer('Cliente ES');
        $customer->setVatId('76.123.456-7');
        $customer->setAddress('Av. Providencia 1234');
        $customer->setPhone('+56 2 22334455');
        $customer->setEmail('facturacion@acme.cl');

        $content = $this->renderQuotation($this->buildQuotation($customer), 'es');

        self::assertStringContainsString('RUT: 77.073.462-2', $content);
        self::assertStringContainsString('Dirección: Avenida Apoquindo 4700, Depto. 11, Las Condes, Santiago', $content);
        self::assertStringContainsString('Teléfono: +56 9 44516977', $content);
        self::assertStringContainsString('Email: info@gpartnerc.com', $content);
        self::assertStringContainsString('Sitio web: www.gpartnerc.com', $content);
        self::assertStringContainsString('Firma del emisor', $content);
        self::assertStringContainsString('Firma del cliente', $content);
        self::assertStringContainsString('Fecha:', $content);

        self::assertStringNotContainsString('quotation.issuer_vat_id', $content);
        self::assertStringNotContainsString('quotation.issuer_address', $content);
        self::assertStringNotContainsString('quotation.issuer_phone', $content);
        self::assertStringNotContainsString('quotation.issuer_email', $content);
        self::assertStringNotContainsString('quotation.issuer_website', $content);
        self::assertStringNotContainsString('quotation.signature_issuer', $content);
        self::assertStringNotContainsString('quotation.signature_customer', $content);
        self::assertStringNotContainsString('quotation.signature_date', $content);
    }
}
