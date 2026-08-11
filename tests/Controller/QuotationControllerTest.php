<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationCatalogItem;
use App\Entity\QuotationLine;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;

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
                'unit' => 'hour',
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
        self::assertStringContainsString('data-unit="hour"', $content);
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
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setUnit('hour')->setQuantity('2.0000')->setUnitPrice('100.0000'));
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
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setUnit('hour')->setQuantity('3.0000')->setUnitPrice('95.0000'));
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

        $quotation = (new Quotation())->setCustomer($customer);
        $quotation->addLine((new QuotationLine())->setDescription('Consulting')->setUnit('hour')->setQuantity('3.0000')->setUnitPrice('95.0000'));
        $em->persist($quotation);
        $em->flush();
        $quotationId = $quotation->getId();

        $this->request($client, '/quotation/' . $quotationId . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form')->form();
        $form['quotation_form[lines][0][quantity]'] = '5.0000';
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/quotation/' . $quotationId));

        $em->clear();
        $reloaded = $em->getRepository(Quotation::class)->find($quotationId);
        self::assertInstanceOf(Quotation::class, $reloaded);
        self::assertCount(1, $reloaded->getLines());
        self::assertEquals(5.0, (float) $reloaded->getLines()->first()->getQuantity());
    }
}
