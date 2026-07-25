<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\FxRate;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FxRateEditForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DatePickerType::class, [
                'label' => 'date',
                'input' => 'datetime_immutable',
                'attr' => [
                    'autofocus' => 'autofocus',
                ],
            ])
            ->add('indicator', ChoiceType::class, [
                'label' => 'indicator',
                'choices' => [
                    'fx_rate.indicator.dolar' => FxRate::INDICATOR_USD,
                    'fx_rate.indicator.uf' => FxRate::INDICATOR_UF,
                ],
            ])
            ->add('rateValue', NumberType::class, [
                'label' => 'value',
                'input' => 'string',
                'scale' => 6,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FxRate::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'fx_rates_edit',
            'attr' => [
                'data-form-event' => 'gppro.fxRateUpdate'
            ],
        ]);
    }
}
