<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\ExpenseApprovalLevel;
use App\Repository\ExpenseApprovalLevelRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ExpenseApprovalLevelRepository::class)]
#[Group('integration')]
class ExpenseApprovalLevelRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ExpenseApprovalLevelRepository
    {
        /** @var ExpenseApprovalLevelRepository $repository */
        $repository = $this->getEntityManager()->getRepository(ExpenseApprovalLevel::class);

        return $repository;
    }

    public function testFindAllOrderedReturnsLevelsAscendingIncludingSeededLevelOne(): void
    {
        $repository = $this->getRepository();

        $levelTwo = (new ExpenseApprovalLevel())->setLevel(2)->setMinAmount(1000000)->setRequiredRole('ROLE_ADMIN');
        $repository->saveLevel($levelTwo);

        $levels = $repository->findAllOrdered();
        $levelNumbers = array_map(static fn (ExpenseApprovalLevel $l): ?int => $l->getLevel(), $levels);

        self::assertSame([1, 2], $levelNumbers);
    }

    public function testDeleteLevelOneIsRefused(): void
    {
        $repository = $this->getRepository();
        $levelOne = $repository->findAllOrdered()[0];
        self::assertSame(1, $levelOne->getLevel());

        $this->expectException(\DomainException::class);
        $repository->deleteLevel($levelOne);
    }

    public function testDeleteNonFirstLevelSucceeds(): void
    {
        $repository = $this->getRepository();
        $levelTwo = (new ExpenseApprovalLevel())->setLevel(2)->setMinAmount(1000000)->setRequiredRole('ROLE_ADMIN');
        $repository->saveLevel($levelTwo);
        $id = $levelTwo->getId();
        self::assertNotNull($id);

        $repository->deleteLevel($levelTwo);

        self::assertNull($repository->find($id));
    }
}
