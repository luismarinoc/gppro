<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Entity\User;
use App\Form\Type\TeamType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the form used to assign a User to teams.
 * @extends AbstractType<User>
 */
final class UserTeamsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A user may only belong to a single team from this screen (UI-only
        // restriction - see the "es uno o el otro" request): the underlying
        // User<->Team relation stays many-to-many (addTeam()/removeTeam(),
        // still used by other flows), so the field is unmapped here and
        // synced manually in ProfileController::teamsAction(). If the user
        // is currently in more than one team (legacy/inconsistent state),
        // nothing is pre-selected - the admin must make an explicit choice
        // instead of one team being silently dropped.
        $user = $options['data'] ?? null;
        $currentTeam = null;

        if ($user instanceof User) {
            $teams = $user->getTeams();
            if (\count($teams) === 1) {
                $currentTeam = $teams[0];
            }
        }

        $builder->add('teams', TeamType::class, [
            'multiple' => false,
            'expanded' => true,
            'mapped' => false,
            'data' => $currentTeam,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'edit_user_teams',
        ]);
    }
}
