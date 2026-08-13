<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Entity\Project;
use App\Form\ExpenseChargeForm;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ExpenseChargeForm::class)]
class ExpenseChargeFormTest extends TypeTestCase
{
    public function testQuotationFieldIsPresent(): void
    {
        $project = new Project();
        $form = $this->factory->createBuilder(ExpenseChargeForm::class, null, ['project' => $project]);

        // 'quotation' uses Symfony's Doctrine bridge EntityType, which
        // requires a ManagerRegistry - only presence is asserted here,
        // matching ProjectEditFormTest's precedent for unresolvable
        // custom/Doctrine-backed fields in this narrow TypeTestCase.
        self::assertTrue($form->has('quotation'));
    }

    public function testProjectOptionIsRequired(): void
    {
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\MissingOptionsException::class);

        $this->factory->createBuilder(ExpenseChargeForm::class);
    }

    public function testProjectOptionIsExposedOnTheForm(): void
    {
        $project = new Project();
        $form = $this->factory->createBuilder(ExpenseChargeForm::class, null, ['project' => $project]);

        self::assertSame($project, $form->getOption('project'));
    }
}
