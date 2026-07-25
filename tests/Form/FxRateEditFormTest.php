<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Configuration\LocaleService;
use App\Entity\FxRate;
use App\Form\FxRateEditForm;
use App\Form\Type\DatePickerType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(FxRateEditForm::class)]
class FxRateEditFormTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // LocaleService is final, so it cannot be mocked: build a real instance
        // covering whatever locale this environment resolves as default.
        $localeService = new LocaleService([
            \Locale::getDefault() => LocaleService::DEFAULT_SETTINGS,
        ]);

        return [
            new PreloadedExtension([new DatePickerType($localeService)], []),
        ];
    }

    public function testFieldsArePresent(): void
    {
        $model = new FxRate();
        $form = $this->factory->createBuilder(FxRateEditForm::class, $model);

        $attr = $form->getFormConfig()->getOption('attr');
        self::assertArrayHasKey('data-form-event', $attr);
        self::assertEquals('gppro.fxRateUpdate', $attr['data-form-event']);

        self::assertTrue($form->has('date'));
        self::assertTrue($form->has('indicator'));
        self::assertTrue($form->has('rateValue'));
    }

    public function testRateValueFieldPreservesDecimalPrecisionAsString(): void
    {
        $model = new FxRate();
        $form = $this->factory->createBuilder(FxRateEditForm::class, $model);

        $rateValue = $form->get('rateValue');

        // rate_value is DECIMAL(15,6) mapped as string in Doctrine: the NumberType
        // MUST keep 'input' => 'string' and 'scale' => 6, otherwise PHP float
        // rounding silently corrupts the last digits of the published rate.
        self::assertEquals('string', $rateValue->getOption('input'));
        self::assertEquals(6, $rateValue->getOption('scale'));
    }

    public function testDateFieldUsesDatetimeImmutableToMatchEntityType(): void
    {
        $model = new FxRate();
        $form = $this->factory->createBuilder(FxRateEditForm::class, $model);

        $date = $form->get('date');

        // FxRate::$date is typed \DateTimeImmutable - the base DateType would
        // otherwise hand back a mutable \DateTime and break the data_class binding.
        self::assertEquals('datetime_immutable', $date->getOption('input'));
    }

    public function testIndicatorChoicesAreRestrictedToUsdAndUf(): void
    {
        $model = new FxRate();
        $form = $this->factory->createBuilder(FxRateEditForm::class, $model);

        $indicator = $form->get('indicator');
        $choices = $indicator->getOption('choices');

        self::assertIsArray($choices);
        self::assertContains(FxRate::INDICATOR_USD, $choices);
        self::assertContains(FxRate::INDICATOR_UF, $choices);
        self::assertCount(2, $choices);
    }

    public function testCsrfTokenIdIsDedicated(): void
    {
        $model = new FxRate();
        $form = $this->factory->createBuilder(FxRateEditForm::class, $model);

        self::assertEquals('fx_rates_edit', $form->getFormConfig()->getOption('csrf_token_id'));
    }
}
