<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Milestone;

use App\Entity\Customer;
use App\Entity\Milestone;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Milestone\InvoiceableMilestoneFinder;
use App\Repository\MilestoneRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceableMilestoneFinder::class)]
class InvoiceableMilestoneFinderTest extends TestCase
{
    private function makeMilestone(?string $value, ?string $currency, ?\DateTime $dueDate = null): Milestone
    {
        $milestone = new Milestone();
        $milestone->setName('m');
        $milestone->setValue($value);
        $milestone->setCurrency($currency);
        $milestone->setDueDate($dueDate);

        return $milestone;
    }

    public function testFindForCustomerFiltersOutNonConvertibleMilestones(): void
    {
        $convertible = $this->makeMilestone('100.0000', 'CLP');
        $notConvertible = $this->makeMilestone('50.0000', 'USD');

        $repository = $this->createMock(MilestoneRepository::class);
        $repository->method('findInvoiceableByCustomer')->willReturn([$convertible, $notConvertible]);

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturnCallback(
            fn (string $amount, string $currency): ?ClpConversion => 'CLP' === $currency
                ? ClpConversion::identity($amount)
                : null
        );

        $sut = new InvoiceableMilestoneFinder($repository, $converter);
        $result = $sut->findForCustomer(new Customer('Test'));

        self::assertSame([$convertible], $result);
    }

    public function testIsConvertibleReturnsFalseWithoutValueOrCurrency(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::never())->method('convert');

        $sut = new InvoiceableMilestoneFinder($this->createMock(MilestoneRepository::class), $converter);

        self::assertFalse($sut->isConvertible($this->makeMilestone(null, null)));
        self::assertFalse($sut->isConvertible($this->makeMilestone('100.0000', null)));
        self::assertFalse($sut->isConvertible($this->makeMilestone(null, 'CLP')));
    }

    public function testIsConvertibleUsesDueDateWhenPresent(): void
    {
        $dueDate = new \DateTime('2026-01-15');
        $milestone = $this->makeMilestone('100.0000', 'USD', $dueDate);

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('100.0000', 'USD', $dueDate)
            ->willReturn(ClpConversion::converted('100.0000', 'USD', '96.00', new \DateTimeImmutable('2026-01-15'), '9600.0000'));

        $sut = new InvoiceableMilestoneFinder($this->createMock(MilestoneRepository::class), $converter);

        self::assertTrue($sut->isConvertible($milestone));
    }
}
