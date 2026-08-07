<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\Event\ConfigureMainMenuEvent;
use App\EventSubscriber\MenuSubscriber;
use KevinPapst\TablerBundle\Helper\ContextHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[CoversClass(MenuSubscriber::class)]
class MenuSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = MenuSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(ConfigureMainMenuEvent::class, $events);
        $methodName = $events[ConfigureMainMenuEvent::class][0];
        self::assertIsString($methodName);
        self::assertTrue(method_exists(MenuSubscriber::class, $methodName));
    }

    public function testQuotationMenuIsVisibleForUsersWithViewPermission(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'view_quotation'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $quotation = $event->getMenu()->findChild('quotations');
        self::assertNotNull($quotation);
        self::assertNull($quotation->getRoute());
        $quotationList = $quotation->findChild('quotation_list');
        self::assertNotNull($quotationList);
        self::assertSame('quotation_list', $quotationList->getRoute());
        self::assertTrue($quotationList->isChildRoute('quotation_create'));
        self::assertTrue($quotationList->isChildRoute('quotation_edit'));
        self::assertTrue($quotationList->isChildRoute('quotation_view'));
        self::assertTrue($quotationList->isChildRoute('quotation_send'));
        self::assertTrue($quotationList->isChildRoute('quotation_convert'));
    }

    public function testQuotationCatalogMenuIsVisibleForCatalogManagers(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => $attribute === 'IS_AUTHENTICATED_REMEMBERED' || $attribute === 'manage_quotation_catalog'
        );
        $security->method('getUser')->willReturn(new User());

        $event = new ConfigureMainMenuEvent();
        (new MenuSubscriber($security, new ContextHelper()))->onMainMenuConfigure($event);

        $catalog = $event->getMenu()->findChild('quotation_catalog');
        self::assertNotNull($catalog);
        self::assertSame('admin_quotation_catalog', $catalog->getRoute());
        self::assertTrue($catalog->isChildRoute('admin_quotation_catalog_create'));
    }
}
