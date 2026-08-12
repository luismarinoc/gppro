<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\QuotationCatalogItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationCatalogItem::class)]
class QuotationCatalogItemTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new QuotationCatalogItem();

        self::assertNull($sut->getId());
        self::assertNull($sut->getName());
        self::assertNull($sut->getDescription());
        self::assertNull($sut->getDefaultPrice());
        self::assertTrue($sut->isActive());
        self::assertNotNull($sut->getCreatedAt());
    }

    public function testSetterAndGetter(): void
    {
        $sut = new QuotationCatalogItem();

        self::assertSame($sut, $sut->setName('Consulting'));
        self::assertSame($sut, $sut->setDescription('Professional consulting service'));
        self::assertSame($sut, $sut->setDefaultPrice('125.5000'));
        self::assertSame($sut, $sut->setActive(false));
        self::assertSame('Consulting', $sut->getName());
        self::assertSame('Professional consulting service', $sut->getDescription());
        self::assertSame('125.5000', $sut->getDefaultPrice());
        self::assertFalse($sut->isActive());
    }
}
