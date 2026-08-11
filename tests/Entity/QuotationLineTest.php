<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\QuotationCatalogItem;
use App\Entity\QuotationLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationLine::class)]
class QuotationLineTest extends TestCase
{
    public function testSnapshotsCanBeSetWithOrWithoutCatalogReference(): void
    {
        $catalogItem = (new QuotationCatalogItem())
            ->setName('Catalog service')
            ->setUnit('hour')
            ->setDefaultPrice('100.0000');
        $sut = (new QuotationLine())
            ->setCatalogItem($catalogItem)
            ->setDescription('Catalog service snapshot')
            ->setUnitPrice('125.0000')
            ->setQuantity('3.0000');

        self::assertSame($catalogItem, $sut->getCatalogItem());
        self::assertSame('Catalog service snapshot', $sut->getDescription());
        self::assertSame('125.0000', $sut->getUnitPrice());
        self::assertSame('3.0000', $sut->getQuantity());

        $sut->setCatalogItem(null);
        self::assertNull($sut->getCatalogItem());
        self::assertSame('Catalog service snapshot', $sut->getDescription());
    }
}
