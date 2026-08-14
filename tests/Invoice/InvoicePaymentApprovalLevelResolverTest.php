<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\InvoicePaymentApprovalLevel;
use App\Invoice\InvoicePaymentApprovalLevelResolver;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePaymentApprovalLevelResolver::class)]
class InvoicePaymentApprovalLevelResolverTest extends TestCase
{
    private function makeLevel(int $level, float $minAmount, string $requiredRole): InvoicePaymentApprovalLevel
    {
        return (new InvoicePaymentApprovalLevel())
            ->setLevel($level)
            ->setMinAmount($minAmount)
            ->setRequiredRole($requiredRole);
    }

    /** @param InvoicePaymentApprovalLevel[] $levels */
    private function repositoryWithLevels(array $levels): InvoicePaymentApprovalLevelRepository
    {
        $repository = $this->createMock(InvoicePaymentApprovalLevelRepository::class);
        $repository->method('findAllOrdered')->willReturn($levels);

        return $repository;
    }

    public function testBelowSecondLevelThresholdResolvesOnlyLevelOne(): void
    {
        $levels = [
            $this->makeLevel(1, 0.0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000.5, 'ROLE_ADMIN'),
        ];
        $sut = new InvoicePaymentApprovalLevelResolver($this->repositoryWithLevels($levels));

        $resolved = $sut->resolve(500_000.0);

        self::assertCount(1, $resolved);
        self::assertSame(1, $resolved[0]->getLevel());
        self::assertSame(1, $sut->requiredLevelsFor(500_000.0));
    }

    public function testAtOrAboveSecondLevelThresholdResolvesBothLevels(): void
    {
        $levels = [
            $this->makeLevel(1, 0.0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000.5, 'ROLE_ADMIN'),
        ];
        $sut = new InvoicePaymentApprovalLevelResolver($this->repositoryWithLevels($levels));

        $resolved = $sut->resolve(2_000_000.0);

        self::assertCount(2, $resolved);
        self::assertSame(2, $sut->requiredLevelsFor(2_000_000.0));
    }

    /**
     * Fractional-float boundary (D5): the algorithm must compare floats
     * exactly at the threshold, not truncate to int like Expense's CLP path.
     */
    public function testAmountExactlyAtFractionalThresholdIsIncluded(): void
    {
        $levels = [
            $this->makeLevel(1, 0.0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000.5, 'ROLE_ADMIN'),
        ];
        $sut = new InvoicePaymentApprovalLevelResolver($this->repositoryWithLevels($levels));

        self::assertSame(2, $sut->requiredLevelsFor(1_000_000.5));
    }

    public function testAmountJustBelowFractionalThresholdExcludesLevel(): void
    {
        $levels = [
            $this->makeLevel(1, 0.0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000.5, 'ROLE_ADMIN'),
        ];
        $sut = new InvoicePaymentApprovalLevelResolver($this->repositoryWithLevels($levels));

        self::assertSame(1, $sut->requiredLevelsFor(1_000_000.49));
    }
}
