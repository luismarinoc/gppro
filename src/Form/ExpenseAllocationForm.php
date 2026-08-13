<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\ExpenseAllocation;
use App\Form\Type\ProjectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseAllocationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', ProjectType::class, [
                'required' => true,
                'query_builder_for_user' => true,
                'label' => 'expense.allocation_project',
            ])
            ->add('percentage', NumberType::class, [
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'label' => 'expense.allocation_percentage',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseAllocation::class,
        ]);
    }
}
