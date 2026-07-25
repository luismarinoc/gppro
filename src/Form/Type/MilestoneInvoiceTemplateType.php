<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Entity\InvoiceTemplate;
use App\Repository\InvoiceTemplateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form field type to select an invoice template for a milestone
 * invoice. Identical to InvoiceTemplateType, except the offered templates
 * are restricted to those configured with a calculator that is compatible
 * with MilestoneInvoiceItem (see MilestoneInvoiceCalculators::COMPATIBLE) —
 * 4 of the 12 shipped calculators unconditionally throw for any milestone
 * invoice because a milestone has no associated user.
 */
final class MilestoneInvoiceTemplateType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'template',
            'help' => 'help.invoiceTemplate',
            'class' => InvoiceTemplate::class,
            'choice_label' => 'name',
            'query_builder' => function (InvoiceTemplateRepository $repository) {
                return $repository->getQueryBuilderForMilestoneFormType();
            }
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
