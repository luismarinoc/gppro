<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Milestone;
use App\Entity\UserType as UserCategory;
use App\Form\Type\InvoiceLabelType;
use App\Form\Type\ProjectType;
use App\Form\Type\TeamType;
use App\Form\Type\UserType;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\MilestoneRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ProjectFormTypeQuery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityEditForm extends AbstractType
{
    use EntityFormTrait;

    public function __construct(private readonly ActivityBoardStateRepository $boardStateRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = null;
        $customer = null;
        $isNew = true;
        $isGlobal = false;
        $options['currency'] = null;

        if (isset($options['data'])) {
            /** @var Activity $entry */
            $entry = $options['data'];
            $isGlobal = $entry->isGlobal();

            if (!$isGlobal) {
                $project = $entry->getProject();
                $customer = $project->getCustomer();
                $options['currency'] = $customer->getCurrency();
            }

            $isNew = $entry->getId() === null;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
                'attr' => [
                    'autofocus' => 'autofocus'
                ],
            ])
            ->add('number', TextType::class, [
                'label' => 'activity_number',
                'required' => false,
                'attr' => [
                    'maxlength' => 10,
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'description',
                'required' => false,
            ])
            ->add('invoiceText', InvoiceLabelType::class)
        ;

        if ($isNew || !$isGlobal) {
            $builder
                ->add('project', ProjectType::class, [
                    'required' => false,
                    'help' => 'help.globalActivity',
                    'query_builder' => function (ProjectRepository $repo) use ($builder, $project, $customer) {
                        $query = new ProjectFormTypeQuery($project, $customer);
                        $query->setUser($builder->getOption('user'));
                        $query->setIgnoreDate(true);
                        $query->setWithCustomer(true);

                        return $repo->getQueryBuilderForFormType($query);
                    },
                ]);
        }

        if (!$isGlobal && $project !== null) {
            $builder
                ->add('milestone', EntityType::class, [
                    'class' => Milestone::class,
                    'label' => 'milestone',
                    'required' => false,
                    'choice_label' => 'name',
                    'query_builder' => function (MilestoneRepository $repo) use ($project) {
                        return $repo->createQueryBuilder('m')
                            ->andWhere('m.project = :project')
                            ->setParameter('project', $project)
                            ->orderBy('m.name', 'ASC');
                    },
                ]);
        }

        if ($isNew) {
            $builder
                ->add('teams', TeamType::class, [
                    'required' => false,
                    'multiple' => true,
                    'expanded' => false,
                    'by_reference' => false,
                    'help' => 'help.teams',
                ]);
        }

        // board-only fields (see ActivityBoardState) - not mapped onto
        // Activity itself (design decision A1/A5), so they are unmapped here
        // and persisted separately by the controller via
        // ActivityBoardService::updateCard(). Only shown for an existing,
        // non-global activity that already belongs to a project - same gate
        // as the milestone field above.
        if (!$isNew && !$isGlobal && $project !== null) {
            $boardState = $this->boardStateRepository->findByActivities([$entry])[$entry->getId()] ?? null;

            $currentTechnicalUser = $boardState?->getTechnicalUser();
            $currentFunctionalUser = $boardState?->getFunctionalUser();

            $builder
                ->add('technicalUser', UserType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'activity_board.technical_user',
                    'data' => $currentTechnicalUser,
                    'user_type' => UserCategory::TECHNICAL,
                    // restrict the dropdown to users with project access, so it
                    // never offers a candidate the save would reject (spec's
                    // "Technical/functional dropdown filtered by project access")
                    'project' => $project,
                    // keeps a previously assigned user selectable even if their
                    // userType no longer matches (changed later, or assigned
                    // before this restriction existed) - never silently drops it
                    'include_users' => $currentTechnicalUser !== null ? [$currentTechnicalUser] : [],
                ])
                ->add('functionalUser', UserType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'activity_board.functional_user',
                    'data' => $currentFunctionalUser,
                    'user_type' => UserCategory::FUNCTIONAL,
                    'project' => $project,
                    'include_users' => $currentFunctionalUser !== null ? [$currentFunctionalUser] : [],
                ]);
        }

        $this->addCommonFields($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'admin_activity_edit',
            'currency' => Customer::DEFAULT_CURRENCY,
            'include_budget' => false,
            'include_time' => false,
            'attr' => [
                'data-form-event' => 'gppro.activityUpdate'
            ],
        ]);
    }
}
