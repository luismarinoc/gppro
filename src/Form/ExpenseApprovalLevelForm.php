<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\ExpenseApprovalLevel;
use App\Form\Type\UserRoleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseApprovalLevelForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('level', IntegerType::class, ['label' => 'expense_approval_level.level'])
            ->add('minAmount', IntegerType::class, ['label' => 'expense_approval_level.min_amount'])
            // Choices are sourced from RoleService (design D1), which merges
            // security.yaml role constants with custom gppro_roles rows.
            ->add('requiredRole', UserRoleType::class, [
                'multiple' => false,
                'expanded' => false,
                'label' => 'expense_approval_level.required_role',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExpenseApprovalLevel::class,
            'csrf_token_id' => 'expense_approval_level_edit',
        ]);
    }
}
