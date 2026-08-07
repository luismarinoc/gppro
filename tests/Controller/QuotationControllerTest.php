<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\QuotationCatalogItem;
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
    }

    public function testRegularUserCannotAccessQuotationManagement(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/quotation/');
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/quotation/catalog/');
    }
}
