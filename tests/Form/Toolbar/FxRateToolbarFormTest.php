<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form\Toolbar;

use App\Entity\FxRate;
use App\Form\Toolbar\FxRateToolbarForm;
use App\Repository\Query\FxRateQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(FxRateToolbarForm::class)]
class FxRateToolbarFormTest extends TypeTestCase
{
    public function testFieldsArePresent(): void
    {
        $query = new FxRateQuery();
        $form = $this->factory->createBuilder(FxRateToolbarForm::class, $query);

        self::assertTrue($form->has('daterange'));
        self::assertTrue($form->has('indicator'));
        self::assertTrue($form->has('size'));
        self::assertTrue($form->has('page'));
        self::assertTrue($form->has('order'));
        self::assertTrue($form->has('orderBy'));
    }

    public function testIndicatorFilterChoicesAreRestrictedToUsdAndUf(): void
    {
        $query = new FxRateQuery();
        $form = $this->factory->createBuilder(FxRateToolbarForm::class, $query);

        $indicator = $form->get('indicator');
        $choices = $indicator->getOption('choices');

        self::assertIsArray($choices);
        self::assertContains(FxRate::INDICATOR_USD, $choices);
        self::assertContains(FxRate::INDICATOR_UF, $choices);
        self::assertCount(2, $choices);
        self::assertFalse($indicator->getOption('required'));
    }

    public function testFormIsNotCsrfProtected(): void
    {
        $query = new FxRateQuery();
        $form = $this->factory->createBuilder(FxRateToolbarForm::class, $query);

        self::assertFalse($form->getFormConfig()->getOption('csrf_protection'));
    }
}
