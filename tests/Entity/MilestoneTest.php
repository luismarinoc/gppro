<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Milestone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Milestone::class)]
class MilestoneTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new Milestone();

        self::assertNull($sut->getValue());
        self::assertNull($sut->getCurrency());
    }

    public function testSetterAndGetter(): void
    {
        $sut = new Milestone();

        self::assertInstanceOf(Milestone::class, $sut->setValue('5000.0000'));
        self::assertEquals('5000.0000', $sut->getValue());

        self::assertInstanceOf(Milestone::class, $sut->setCurrency('USD'));
        self::assertEquals('USD', $sut->getCurrency());
    }

    public function testSetterAndGetterAllowNull(): void
    {
        $sut = new Milestone();
        $sut->setValue('123.4500');
        $sut->setCurrency('EUR');

        self::assertInstanceOf(Milestone::class, $sut->setValue(null));
        self::assertNull($sut->getValue());

        self::assertInstanceOf(Milestone::class, $sut->setCurrency(null));
        self::assertNull($sut->getCurrency());
    }
}
