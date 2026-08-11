<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Form\Type\CustomerType;
use App\Form\Type\DatePickerType;
use App\Form\Type\ProjectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class QuotationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Quotation $quotation */
        $quotation = $builder->getData();
        $customer = $options['customer'] ?? $quotation?->getCustomer();

        $builder
            ->add('customer', CustomerType::class, [
                'query_builder_for_user' => true,
                'required' => true,
            ])
            ->add('project', ProjectType::class, [
                'required' => false,
                'customers' => $customer instanceof Customer ? $customer : null,
                'query_builder_for_user' => true,
                'ignore_date' => true,
            ])
            ->add('validUntil', DatePickerType::class, ['required' => false])
            ->add('tax', NumberType::class, ['required' => false, 'scale' => 4, 'html5' => true, 'label' => 'quotation.tax', 'help' => 'quotation.percentage_help'])
            ->add('discount', NumberType::class, ['required' => false, 'scale' => 4, 'html5' => true, 'label' => 'quotation.discount', 'help' => 'quotation.percentage_help'])
            ->add('surcharge', NumberType::class, ['required' => false, 'scale' => 4, 'html5' => true, 'label' => 'quotation.surcharge', 'help' => 'quotation.percentage_help'])
            ->add('lines', CollectionType::class, [
                'entry_type' => QuotationLineForm::class,
                'entry_options' => ['catalog_items' => $options['catalog_items']],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quotation::class,
            'catalog_items' => [],
            'customer' => null,
            'csrf_token_id' => 'quotation',
        ]);
        $resolver->setAllowedTypes('catalog_items', 'array');
        $resolver->setAllowedTypes('customer', [Customer::class, 'null']);
    }
}
