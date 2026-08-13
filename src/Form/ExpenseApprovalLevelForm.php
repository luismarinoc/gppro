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
use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ExpenseApprovalLevelForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ExpenseApprovalLevel|null $level */
        $level = \array_key_exists('data', $options) ? $options['data'] : null;

        $builder
            ->add('level', IntegerType::class, ['label' => 'expense_approval_level.level'])
            ->add('minAmount', IntegerType::class, ['label' => 'expense_approval_level.min_amount'])
            // Choices are sourced from RoleService (design D1), which merges
            // security.yaml role constants with custom gppro_roles rows.
            ->add('requiredRole', UserRoleType::class, [
                'multiple' => false,
                'expanded' => false,
                'label' => 'expense_approval_level.required_role',
            ])
            ->add('approverUser', UserType::class, [
                'required' => false,
                'label' => 'expense_approval_level.approver_user',
                'help' => 'expense_approval_level.approver_user.help',
                // keep an already-assigned but disabled user selectable (D6)
                'include_users' => ($level?->getApproverUser() !== null ? [$level->getApproverUser()] : []),
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
