<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\ExpenseApprovalLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ExpenseApprovalLevel::class)]
#[Group('integration')]
class ExpenseApprovalLevelValidationTest extends KernelTestCase
{
    use EntityValidationTestTrait;

    public function testLevel1MustHaveZeroMinAmount(): void
    {
        $entity = (new ExpenseApprovalLevel())
            ->setLevel(1)
            ->setMinAmount(100)
            ->setRequiredRole('ROLE_TEAMLEAD');

        $this->assertHasViolationForField($entity, 'minAmount');
    }

    public function testLevel1WithZeroMinAmountIsValid(): void
    {
        $entity = (new ExpenseApprovalLevel())
            ->setLevel(1)
            ->setMinAmount(0)
            ->setRequiredRole('ROLE_TEAMLEAD');

        $this->assertHasNoViolations($entity);
    }

    public function testLevelAboveOneIsNotForcedToZero(): void
    {
        $entity = (new ExpenseApprovalLevel())
            ->setLevel(2)
            ->setMinAmount(1000000)
            ->setRequiredRole('ROLE_ADMIN');

        $this->assertHasNoViolations($entity);
    }

    public function testInvalidRoleIsRejected(): void
    {
        $entity = (new ExpenseApprovalLevel())
            ->setLevel(1)
            ->setMinAmount(0)
            ->setRequiredRole('NOT_A_ROLE');

        $this->assertHasViolationForField($entity, 'requiredRole');
    }
}
