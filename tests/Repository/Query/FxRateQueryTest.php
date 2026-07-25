<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository\Query;

use App\Entity\FxRate;
use App\Repository\Query\FxRateQuery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FxRateQuery::class)]
class FxRateQueryTest extends BaseQueryTest
{
    public function testQuery(): void
    {
        $sut = new FxRateQuery();

        $this->assertBaseQuery($sut, 'date', FxRateQuery::ORDER_DESC);
        $this->assertDateRangeTrait($sut);

        self::assertNull($sut->getIndicator());
        self::assertInstanceOf(FxRateQuery::class, $sut->setIndicator(FxRate::INDICATOR_USD));
        self::assertEquals(FxRate::INDICATOR_USD, $sut->getIndicator());

        self::assertInstanceOf(FxRateQuery::class, $sut->setIndicator(FxRate::INDICATOR_UF));
        self::assertEquals(FxRate::INDICATOR_UF, $sut->getIndicator());
    }
}
