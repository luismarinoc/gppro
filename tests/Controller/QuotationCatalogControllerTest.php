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
class QuotationCatalogControllerTest extends AbstractControllerBaseTestCase
{
    public function testCatalogRoutesAreSecured(): void
    {
        $this->assertUrlIsSecured('/admin/quotation/catalog/');
    }

    public function testTeamleadCannotAccessCatalogManagement(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_TEAMLEAD, '/admin/quotation/catalog/');
    }

    /**
     * Row-click-to-edit consistency (see quotation/index.html.twig for the
     * established pattern): the list row itself must carry the alternative-
     * link data-href pointing at the same edit route the "Editar" text link
     * already uses, so clicking anywhere on the row opens the edit form.
     */
    public function testIndexRowExposesDataHrefForRowClickToEdit(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $item = new QuotationCatalogItem();
        $item->setName('Row click catalog item ' . uniqid());
        $item->setDefaultPrice('1000');
        $em = $this->getEntityManager();
        $em->persist($item);
        $em->flush();
        $itemId = $item->getId();

        $this->request($client, '/admin/quotation/catalog/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString(
            'data-href="' . $this->createUrl('/admin/quotation/catalog/' . $itemId . '/edit') . '"',
            $content
        );
        self::assertMatchesRegularExpression('/<tr[^>]*class="[^"]*alternative-link[^"]*"/', $content);
    }
}
