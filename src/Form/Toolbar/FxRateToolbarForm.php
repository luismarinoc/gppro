<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Toolbar;

use App\Entity\FxRate;
use App\Repository\Query\FxRateQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<FxRateQuery>
 */
final class FxRateToolbarForm extends AbstractType
{
    use ToolbarFormTrait;

    public const FX_RATE_ORDER_ALLOWED = ['date', 'indicator'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addDateRange($builder, ['timezone' => $options['timezone']]);
        $builder->add('indicator', ChoiceType::class, [
            'label' => 'indicator',
            'required' => false,
            'placeholder' => 'all',
            'choices' => [
                'fx_rate.indicator.dolar' => FxRate::INDICATOR_USD,
                'fx_rate.indicator.uf' => FxRate::INDICATOR_UF,
            ],
        ]);
        $this->addPageSizeChoice($builder);
        $this->addHiddenPagination($builder);
        $this->addOrder($builder);
        $this->addOrderBy($builder, self::FX_RATE_ORDER_ALLOWED);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FxRateQuery::class,
            'csrf_protection' => false,
            'timezone' => date_default_timezone_get(),
        ]);
    }
}
