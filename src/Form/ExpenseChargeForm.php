<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Project;
use App\Entity\Quotation;
use App\Repository\QuotationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Not entity-mapped: the charge action posts a target quotation id (design
 * D5); the {@see \App\Entity\ExpenseAllocation} being charged is resolved by
 * the controller from the route, not from this form.
 */
final class ExpenseChargeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder->add('quotation', EntityType::class, [
            'class' => Quotation::class,
            'label' => 'expense.charge_quotation',
            'placeholder' => 'expense.charge_quotation_placeholder',
            'choice_label' => static fn (Quotation $quotation): string => \sprintf(
                '#%d (%s)',
                (int) $quotation->getId(),
                $quotation->getValidUntil()?->format('Y-m-d') ?? '',
            ),
            // Only draft CLP quotations of the same project can be
            // cross-charged (spec: "Cross-charge an approved allocation", D5).
            'query_builder' => static function (QuotationRepository $repo) use ($project) {
                return $repo->createQueryBuilder('q')
                    ->andWhere('q.project = :project')
                    ->andWhere('q.status = :status')
                    ->andWhere('q.currency = :currency')
                    ->setParameter('project', $project)
                    ->setParameter('status', Quotation::STATUS_DRAFT)
                    ->setParameter('currency', Quotation::CURRENCY_CLP)
                    ->orderBy('q.createdAt', 'DESC');
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_token_id' => 'expense_charge',
        ]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
