<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Toolbar;

use App\Entity\Milestone;
use App\Entity\User;
use App\Form\Type\DatePickerType;
use App\Form\Type\InvoiceTemplateType;
use App\Repository\MilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\ProjectFormTypeQuery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the form used for filtering timesheet entries for invoices.
 * @extends AbstractType<InvoiceQuery>
 */
final class InvoiceToolbarForm extends AbstractType
{
    use ToolbarFormTrait;

    public function __construct(private readonly ProjectRepository $projectRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addSearchTermInputField($builder);
        $this->addDateRange($builder, ['timezone' => $options['timezone']]);
        $this->addCustomerMultiChoice($builder, ['start_date_param' => null, 'end_date_param' => null, 'ignore_date' => true], true);
        $this->addProjectMultiChoice($builder, ['ignore_date' => true], true, true);
        $this->addActivitySelect($builder, [], true, true, false);

        $user = $options['user'];
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Missing required form option "user"');
        }

        $builder->add('milestone', EntityType::class, [
            'class' => Milestone::class,
            'label' => 'milestone',
            'required' => false,
            'placeholder' => '',
            'choice_label' => function (Milestone $milestone) {
                return ($milestone->getProject()?->getName() ?? '') . ' - ' . $milestone->getName();
            },
            'query_builder' => function (MilestoneRepository $repo) use ($user) {
                $projectQuery = new ProjectFormTypeQuery();
                $projectQuery->setUser($user);
                $projectQuery->setIgnoreDate(true);
                $projects = $this->projectRepository->getQueryBuilderForFormType($projectQuery)->getQuery()->getResult();

                return $repo->createQueryBuilder('m')
                    ->andWhere('m.project IN (:projects)')
                    ->setParameter('projects', $projects)
                    ->orderBy('m.name', 'ASC');
            },
        ]);
        $this->addTagInputField($builder);
        if ($options['include_user']) {
            $this->addUsersChoice($builder);
            $this->addTeamsChoice($builder);
        }
        $this->addExportStateChoice($builder);
        $builder->add('invoiceDate', DatePickerType::class, [
            'required' => true,
        ]);
        $this->addTemplateChoice($builder);
    }

    protected function addTemplateChoice(FormBuilderInterface $builder): void
    {
        $builder->add('template', InvoiceTemplateType::class, [
            'required' => true,
            'placeholder' => null,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceQuery::class,
            'csrf_protection' => false,
            'include_user' => true,
            'timezone' => date_default_timezone_get(),
        ]);
        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', User::class);
    }
}
