<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\QuotationLine;
use App\Invoice\QuotationInvoiceItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationInvoiceItem::class)]
final class QuotationInvoiceItemTest extends TestCase
{
    public function testPreservesLineSnapshotValuesForInvoiceCalculators(): void
    {
        $line = new QuotationLine();
        $line->setDescription('Catalog snapshot');
        $line->setQuantity('2.5000');
        $line->setUnitPrice('120.0000');

        $item = new QuotationInvoiceItem($line);

        self::assertSame('Catalog snapshot', $item->getDescription());
        self::assertSame(2.5, $item->getAmount());
        self::assertSame(120.0, $item->getFixedRate());
        self::assertSame(300.0, $item->getRate());
        self::assertSame('quotation', $item->getType());
    }
}
