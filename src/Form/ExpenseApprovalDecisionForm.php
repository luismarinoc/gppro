<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Not entity-mapped: the decision (approved/rejected) is determined by which
 * controller action is invoked, not by a form field (design D2) - this form
 * only captures the optional {@see \App\Entity\ExpenseApproval::$note}.
 */
final class ExpenseApprovalDecisionForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('note', TextareaType::class, [
            'required' => false,
            'label' => 'expense.approval_note',
            'constraints' => [
                new Length(max: 1000),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'expense_approval_decision',
        ]);
    }
}
