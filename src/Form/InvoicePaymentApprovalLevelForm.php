<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\InvoicePaymentApprovalLevel;
use App\Form\Type\UserRoleType;
use App\Form\Type\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InvoicePaymentApprovalLevelForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var InvoicePaymentApprovalLevel|null $level */
        $level = \array_key_exists('data', $options) ? $options['data'] : null;

        $builder
            ->add('level', IntegerType::class, ['label' => 'invoice_payment_approval_level.level'])
            // D5: minAmount is float, matches Invoice::getTotal(): float
            ->add('minAmount', NumberType::class, ['label' => 'invoice_payment_approval_level.min_amount'])
            // Choices are sourced from RoleService (mirrors D1 on the Expense
            // form), which merges security.yaml role constants with custom
            // gppro_roles rows.
            ->add('requiredRole', UserRoleType::class, [
                'multiple' => false,
                'expanded' => false,
                'label' => 'invoice_payment_approval_level.required_role',
            ])
            ->add('approverUser', UserType::class, [
                'required' => false,
                'label' => 'invoice_payment_approval_level.approver_user',
                'help' => 'invoice_payment_approval_level.approver_user.help',
                // keep an already-assigned but disabled user selectable
                'include_users' => ($level?->getApproverUser() !== null ? [$level->getApproverUser()] : []),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoicePaymentApprovalLevel::class,
            'csrf_token_id' => 'invoice_payment_approval_level_edit',
        ]);
    }
}
