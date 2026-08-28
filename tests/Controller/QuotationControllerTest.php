<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Customer;
use App\Entity\FxRate;
use App\Entity\Quotation;
use App\Entity\QuotationCatalogItem;
use App\Entity\QuotationLine;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class QuotationControllerTest extends AbstractControllerBaseTestCase
{
    public function testQuotationAndCatalogRoutesAreSecured(): void
    {
        $this->assertUrlIsSecured('/quotation/');
        $this->assertUrlIsSecured('/admin/quotation/catalog/');
    }

    public function testAdminCanOpenQuotationCreationForm(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/quotation/create');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('data-add-catalog-line', $content);
        self::assertStringContainsString('data-add-manual-line', $content);
        self::assertStringContainsString('data-quotation-lines-empty', $content);
        self::assertStringContainsString('__name__', $content);
        self::assertCount(0, $client->getCrawler()->filter('[data-quotation-lines] > [data-quotation-line]'));
        self::assertStringContainsString('data-remove-quotation-line', $content);
        self::assertStringContainsString('data-line-subtotal', $content);
        self::assertStringContainsString('SAP', $content);
    }

    public function testAdminCanListAndCreateCatalogItems(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/admin/quotation/catalog/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->request($client, '/admin/quotation/catalog/create');
        self::assertTrue($client->getResponse()->isSuccessful());
        $form = $client->getCrawler()->filter('form[name=quotation_catalog_item_form]')->form();
        $client->submit($form, [
            'quotation_catalog_item_form' => [
                'name' => 'Consulting',
                'description' => 'Consulting service',
                'defaultPrice' => '100.00',
                'active' => 1,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/quotation/catalog/'));
        $item = $this->getEntityManager()->getRepository(QuotationCatalogItem::class)->findOneBy(['name' => 'Consulting']);
        self::assertInstanceOf(QuotationCatalogItem::class, $item);
        self::assertEquals(100.0, (float) $item->getDefaultPrice());

        $this->request($client, '/quotation/create');
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('data-description="Consulting service"', $content);
        self::assertStringContainsString('data-unit-price="100.0000"', $content);
    }

    public function testRegularUserCannotAccessQuotationManagement(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/quotation/');
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/quotation/catalog/');
    }

    public function testAdminCanViewSavedQuotationAsPdf(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('PDF view test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer);
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('2.0000')->setUnitPrice('100.0000'));
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/' . $quotation->getId());
        self::assertTrue($client->getResponse()->isSuccessful());
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString($this->createUrl('/quotation/' . $quotation->getId() . '/pdf'), $content);

        $this->request($client, '/quotation/' . $quotation->getId() . '/pdf');
        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertStringContainsString('application/pdf', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    public function testEditingASavedQuotationShowsLinesReadOnlyWithAnEditToggle(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Edit toggle test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setSurcharge('10')->setTax('19');
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('3.0000')->setUnitPrice('95.0000'));
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/' . $quotation->getId() . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        self::assertStringContainsString('data-quotation-line-view', $content);
        self::assertStringContainsString('data-edit-quotation-line', $content);
        self::assertStringContainsString('Consulting', $content);
        self::assertStringContainsString('data-quotation-line-edit', $content);

        $crawler = $client->getCrawler();
        self::assertCount(1, $crawler->filter('[data-quotation-lines] > [data-quotation-line]'));
        self::assertStringContainsString('d-none', $crawler->filter('[data-quotation-line-edit]')->attr('class') ?? '');

        // the actual input values must still be present and machine-parseable (period-decimal), even hidden
        // scoped to the real saved line, not the empty <template> prototype (which DomCrawler parses as live DOM)
        $quantityValue = $crawler->filter('[data-quotation-lines] > [data-quotation-line] input[name$="[quantity]"]')->first()->attr('value');
        self::assertNotNull($quantityValue);
        self::assertMatchesRegularExpression('/^\d+(\.\d+)?$/', $quantityValue);
    }

    public function testAdminCanSubmitEditFormForASavedQuotationWithLines(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Edit submit test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setValidUntil(new \DateTimeImmutable('2026-08-20'));
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('3.0000')->setUnitPrice('95.0000'));
        $em->persist($quotation);
        $em->flush();
        $quotationId = $quotation->getId();

        $this->request($client, '/quotation/' . $quotationId . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form')->form();
        $form['quotation_form[lines][0][quantity]'] = '5.0000';
        $form['quotation_form[currency]'] = 'USD';
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/quotation/' . $quotationId));

        $em->clear();
        $reloaded = $em->getRepository(Quotation::class)->find($quotationId);
        self::assertInstanceOf(Quotation::class, $reloaded);
        self::assertCount(1, $reloaded->getLines());
        $reloadedLine = $reloaded->getLines()->first();
        self::assertInstanceOf(QuotationLine::class, $reloadedLine);
        self::assertEquals(5.0, (float) $reloadedLine->getQuantity());
        self::assertSame('USD', $reloaded->getCurrency());
    }

    public function testEditFormRendersAndSavesTheNotesField(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Notes field test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setValidUntil(new \DateTimeImmutable('2026-08-20'));
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('1.0000')->setUnitPrice('95.0000'));
        $em->persist($quotation);
        $em->flush();
        $quotationId = $quotation->getId();

        $this->request($client, '/quotation/' . $quotationId . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertGreaterThan(0, $client->getCrawler()->filter('textarea[name="quotation_form[notes]"]')->count(), 'The notes field must actually render in the edit form.');

        $form = $client->getCrawler()->filter('form')->form();
        $form['quotation_form[notes]'] = 'Precios válidos por 30 días.';
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/quotation/' . $quotationId));

        $em->clear();
        $reloaded = $em->getRepository(Quotation::class)->find($quotationId);
        self::assertInstanceOf(Quotation::class, $reloaded);
        self::assertSame('Precios válidos por 30 días.', $reloaded->getNotes());
    }

    public function testEditFormOffersTheThreeAllowedCurrencies(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Currency select test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer);
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('1')->setUnitPrice('1'));
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/' . $quotation->getId() . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());

        $options = $client->getCrawler()->filter('select[name="quotation_form[currency]"] option')
            ->each(static fn ($node) => $node->attr('value'));

        self::assertSame(['CLP', 'USD', 'CLF'], $options);
    }

    public function testQuotationListRowIsClickableToEditForUsersWithEditPermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Clickable row test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer);
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $row = $client->getCrawler()->filter('tr[data-href$="/quotation/' . $quotation->getId() . '/edit"]');
        self::assertCount(1, $row);
        self::assertStringContainsString('alternative-link', $row->attr('class') ?? '');
    }

    public function testViewAndPdfShowAmountsInClpOnlyForNonClpQuotationsUsingTheValidUntilDate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable('2026-08-01'));
        $fxRate->setIndicator(FxRate::INDICATOR_USD);
        $fxRate->setRateValue('950.000000');
        $em->persist($fxRate);

        $customer = new Customer('FX equivalent test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setCurrency(Quotation::CURRENCY_USD);
        $quotation->setValidUntil(new \DateTimeImmutable('2026-08-05'));
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('2')->setUnitPrice('100'));
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/' . $quotation->getId());
        self::assertTrue($client->getResponse()->isSuccessful());
        $viewContent = (string) $client->getResponse()->getContent();
        // 2 x 100 USD @ 950 = 190,000 CLP total, shown directly, no UF/USD or FX-rate mention
        self::assertStringContainsString('190', $viewContent);
        self::assertStringNotContainsString('CLP equivalent', $viewContent);
        self::assertStringNotContainsString('1 USD = 950.00 CLP', $viewContent);

        $this->request($client, '/quotation/' . $quotation->getId() . '/pdf');
        self::assertTrue($client->getResponse()->isSuccessful());
        $pdfContent = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('CLP equivalent', $pdfContent);
    }

    public function testViewRedirectsWithFlashErrorWhenNoClpExchangeRateIsAvailable(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('No FX rate test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setCurrency(Quotation::CURRENCY_USD);
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('1')->setUnitPrice('100'));
        $em->persist($quotation);
        $em->flush();

        $this->request($client, '/quotation/' . $quotation->getId());
        self::assertTrue($client->getResponse()->isRedirect($this->createUrl('/quotation/')));
    }

    public function testFxRateEndpointReturnsTheRateAsOfTheGivenDate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable('2026-08-01'));
        $fxRate->setIndicator(FxRate::INDICATOR_USD);
        $fxRate->setRateValue('950.000000');
        $em->persist($fxRate);
        $em->flush();

        $this->request($client, '/quotation/fx-rate?currency=USD&date=2026-08-05');
        self::assertTrue($client->getResponse()->isSuccessful());
        $data = $this->decodeJsonResponse($client);
        self::assertTrue($data['available']);
        self::assertSame('950.000000', $data['rate']);
        self::assertSame('2026-08-01', $data['rateDate']);

        $this->request($client, '/quotation/fx-rate?currency=CLP&date=2026-08-05');
        self::assertFalse($this->decodeJsonResponse($client)['available']);

        $this->request($client, '/quotation/fx-rate?currency=CLF&date=2026-08-05');
        self::assertFalse($this->decodeJsonResponse($client)['available']);

        $this->request($client, '/quotation/fx-rate?currency=BOGUS&date=2026-08-05');
        self::assertFalse($this->decodeJsonResponse($client)['available']);
    }

    /** @return array<string, mixed> */
    private function decodeJsonResponse(HttpKernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        $result = [];
        foreach ($data as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
    }

    public function testEditFormRequiresTheValidUntilDate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Required date test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setValidUntil(new \DateTimeImmutable('2026-08-20'));
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('1')->setUnitPrice('1'));
        $em->persist($quotation);
        $em->flush();
        $quotationId = $quotation->getId();

        $this->request($client, '/quotation/' . $quotationId . '/edit');
        $form = $client->getCrawler()->filter('form')->form();
        $form['quotation_form[validUntil]'] = '';
        $client->submit($form);

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertFalse($client->getResponse()->isRedirect());

        $em->clear();
        $reloaded = $em->getRepository(Quotation::class)->find($quotationId);
        self::assertInstanceOf(Quotation::class, $reloaded);
        self::assertNotNull($reloaded->getValidUntil());
    }

    public function testPaymentTermCanBeSetToThirtySixtyOrNinetyDays(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Payment term test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $quotation = (new Quotation())->setCustomer($customer)->setValidUntil(new \DateTimeImmutable('2026-08-20'));
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setQuantity('1')->setUnitPrice('1'));
        $em->persist($quotation);
        $em->flush();
        $quotationId = $quotation->getId();

        $this->request($client, '/quotation/' . $quotationId . '/edit');
        $options = $client->getCrawler()->filter('select[name="quotation_form[paymentTermDays]"] option:not([value=""])')
            ->each(static fn ($node) => $node->attr('value'));
        self::assertSame(['30', '60', '90'], $options);

        $form = $client->getCrawler()->filter('form')->form();
        $form['quotation_form[paymentTermDays]'] = '60';
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/quotation/' . $quotationId));

        $em->clear();
        $reloaded = $em->getRepository(Quotation::class)->find($quotationId);
        self::assertInstanceOf(Quotation::class, $reloaded);
        self::assertSame(60, $reloaded->getPaymentTermDays());
    }
}
