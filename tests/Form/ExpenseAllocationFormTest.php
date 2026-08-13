<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Entity\ExpenseAllocation;
use App\Form\ExpenseAllocationForm;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ExpenseAllocationForm::class)]
class ExpenseAllocationFormTest extends TypeTestCase
{
    public function testFieldsArePresent(): void
    {
        $model = new ExpenseAllocation();
        $form = $this->factory->createBuilder(ExpenseAllocationForm::class, $model);

        // 'project' uses App\Form\Type\ProjectType, which requires
        // ProjectHelper/CustomerHelper (both final, unmockable) - only
        // presence is asserted, matching ProjectEditFormTest's precedent.
        self::assertTrue($form->has('project'));
        self::assertTrue($form->has('percentage'));
    }

    public function testPercentageFieldPreservesDecimalPrecisionAsString(): void
    {
        $model = new ExpenseAllocation();
        $form = $this->factory->createBuilder(ExpenseAllocationForm::class, $model);

        $percentage = $form->get('percentage');

        // ExpenseAllocation::$percentage is DECIMAL(5,2) mapped as string in
        // Doctrine: NumberType must keep 'input' => 'string' and 'scale' => 2,
        // otherwise PHP float rounding silently corrupts the split (same rule
        // as FxRate::$rateValue / Milestone::$value).
        self::assertEquals('string', $percentage->getOption('input'));
        self::assertEquals(2, $percentage->getOption('scale'));
        self::assertTrue($percentage->getOption('html5'));
        self::assertTrue($percentage->getOption('required'));
    }
}
