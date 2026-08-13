<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\ExpenseApprovalLevel;
use App\Expense\ApprovalLevelResolver;
use App\Repository\ExpenseApprovalLevelRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApprovalLevelResolver::class)]
class ApprovalLevelResolverTest extends TestCase
{
    private function makeLevel(int $level, int $minAmount, string $requiredRole): ExpenseApprovalLevel
    {
        return (new ExpenseApprovalLevel())
            ->setLevel($level)
            ->setMinAmount($minAmount)
            ->setRequiredRole($requiredRole);
    }

    /** @return ExpenseApprovalLevelRepository&\PHPUnit\Framework\MockObject\MockObject */
    private function repositoryWithLevels(array $levels): ExpenseApprovalLevelRepository
    {
        $repository = $this->createMock(ExpenseApprovalLevelRepository::class);
        $repository->method('findAllOrdered')->willReturn($levels);

        return $repository;
    }

    public function testBelowSecondLevelThresholdResolvesOnlyLevelOne(): void
    {
        $levels = [
            $this->makeLevel(1, 0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000, 'ROLE_ADMIN'),
        ];
        $sut = new ApprovalLevelResolver($this->repositoryWithLevels($levels));

        $resolved = $sut->resolve(500_000);

        self::assertCount(1, $resolved);
        self::assertSame(1, $resolved[0]->getLevel());
        self::assertSame(1, $sut->requiredLevelsFor(500_000));
    }

    public function testAtOrAboveSecondLevelThresholdResolvesBothLevels(): void
    {
        $levels = [
            $this->makeLevel(1, 0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000, 'ROLE_ADMIN'),
        ];
        $sut = new ApprovalLevelResolver($this->repositoryWithLevels($levels));

        $resolved = $sut->resolve(2_000_000);

        self::assertCount(2, $resolved);
        self::assertSame(2, $sut->requiredLevelsFor(2_000_000));
    }

    public function testAmountExactlyAtThresholdIsIncluded(): void
    {
        $levels = [
            $this->makeLevel(1, 0, 'ROLE_TEAMLEAD'),
            $this->makeLevel(2, 1_000_000, 'ROLE_ADMIN'),
        ];
        $sut = new ApprovalLevelResolver($this->repositoryWithLevels($levels));

        self::assertSame(2, $sut->requiredLevelsFor(1_000_000));
    }
}
