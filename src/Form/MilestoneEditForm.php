<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Milestone;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MilestoneEditForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
            ])
            ->add('dueDate', DatePickerType::class, [
                'label' => 'due_date',
                'required' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'comment',
                'required' => false,
            ])
            ->add('value', NumberType::class, [
                'label' => 'value',
                'input' => 'string',
                'scale' => 4,
                'required' => false,
            ])
            ->add('currency', CurrencyType::class, [
                'label' => 'currency',
                'required' => false,
                // Restrict to the ISO codes ClpConverter can actually resolve
                // (Milestone::SUPPORTED_CURRENCIES) instead of the full ICU currency
                // list. Passing 'choice_loader' => null overrides CurrencyType's
                // default Intl-backed loader, which otherwise takes priority over
                // the 'choices' option (see Symfony\...\ChoiceType::createChoiceList).
                // CLF is displayed as "UF" (Chile's "Unidad de Fomento", its only
                // common name) while still storing/validating the ISO code 'CLF'.
                'choice_loader' => null,
                'choices' => [
                    'CLP' => 'CLP',
                    'USD' => 'USD',
                    'UF' => 'CLF',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Milestone::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'admin_milestone_edit',
            'attr' => [
                'data-form-event' => 'gppro.milestoneUpdate'
            ],
        ]);
    }
}
