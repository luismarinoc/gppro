<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Expense::class)]
#[Group('integration')]
class ExpenseValidationTest extends KernelTestCase
{
    use EntityValidationTestTrait;

    private function validExpense(): Expense
    {
        $expense = (new Expense())
            ->setDescription('Office rent')
            ->setAmount(100000)
            ->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        $expense->addAllocation((new ExpenseAllocation())->setProject(new Project())->setPercentage('100.00'));

        return $expense;
    }

    public function testInvalidCategoryIsRejected(): void
    {
        $entity = $this->validExpense()->setCategory('gasoline');

        $this->assertHasViolationForField($entity, 'category');
    }

    public function testNullCategoryProducesNoViolation(): void
    {
        $entity = $this->validExpense()->setCategory(null);

        $this->assertHasNoViolations($entity);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validCategoryProvider(): array
    {
        return [
            'rent' => [Expense::CATEGORY_RENT],
            'equipment' => [Expense::CATEGORY_EQUIPMENT],
            'electricity' => [Expense::CATEGORY_ELECTRICITY],
            'phone' => [Expense::CATEGORY_PHONE],
            'services' => [Expense::CATEGORY_SERVICES],
            'other' => [Expense::CATEGORY_OTHER],
        ];
    }

    #[DataProvider('validCategoryProvider')]
    public function testEachCategoryConstantIsValid(string $category): void
    {
        $entity = $this->validExpense()->setCategory($category);

        $this->assertHasNoViolations($entity);
    }
}
