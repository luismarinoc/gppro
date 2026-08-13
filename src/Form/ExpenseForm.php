<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Expense;
use App\Form\Type\DatePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, ['label' => 'expense.description'])
            ->add('amount', IntegerType::class, ['label' => 'expense.amount'])
            ->add('expenseDate', DatePickerType::class, [
                'label' => 'expense.date',
                'input' => 'datetime_immutable',
            ])
            ->add('recurrence', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'expense.recurrence_placeholder',
                'label' => 'expense.recurrence',
                'choices' => [
                    'expense.recurrence_month' => Expense::RECURRENCE_MONTH,
                ],
            ])
            ->add('allocations', CollectionType::class, [
                'entry_type' => ExpenseAllocationForm::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'label' => 'expense.allocations',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
            'csrf_token_id' => 'expense_edit',
        ]);
    }
}
