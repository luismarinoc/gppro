<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UserSubscriber extends AbstractActionsSubscriber
{
    public function __construct(
        AuthorizationCheckerInterface $auth,
        UrlGeneratorInterface $urlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    )
    {
        parent::__construct($auth, $urlGenerator);
    }

    public static function getActionName(): string
    {
        return 'user';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        if (!\array_key_exists('user', $payload)) {
            return;
        }

        $user = $payload['user'];

        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        if ($this->isGranted('view', $user)) {
            $event->addAction('profile-stats', ['icon' => 'avatar', 'url' => $this->path('user_profile', ['username' => $user->getUserIdentifier()]), 'title' => 'profile-stats']);
            $event->addDivider();
        }

        $subEvent = new PageActionsEvent($user, ['user' => $user], 'user_forms', 'index');
        $this->eventDispatcher->dispatch($subEvent, $subEvent->getEventName());

        foreach ($subEvent->getActions() as $id => $action) {
            $event->addActionToSubmenu('edit', $id, $action);
        }

        if ($this->isGranted('hours', $user)) {
            $event->addActionToSubmenu('report', 'work_times', ['url' => $this->path('user_contract', ['user' => $user->getId()]), 'title' => 'work_times']);
        }

        if (($event->getUser()->getId() === $user->getId() && $this->isGranted('report:user')) || $this->isGranted('report:other')) {
            $event->addActionToSubmenu('report', 'weekly', ['url' => $this->path('report_user_week', ['user' => $user->getId()]), 'translation_domain' => 'reporting', 'title' => 'report_user_week']);
            $event->addActionToSubmenu('report', 'monthly', ['url' => $this->path('report_user_month', ['user' => $user->getId()]), 'translation_domain' => 'reporting', 'title' => 'report_user_month']);
            $event->addActionToSubmenu('report', 'yearly', ['url' => $this->path('report_user_year', ['user' => $user->getId()]), 'translation_domain' => 'reporting', 'title' => 'report_user_year']);
        }

        if ($user->isEnabled() && $this->isGranted('view_other_timesheet')) {
            $event->addActionToSubmenu('filter', 'timesheet', ['url' => $this->path('admin_timesheet', ['users[]' => $user->getId()]), 'title' => 'timesheet.filter']);
        }

        if ($this->isGranted('view_team')) {
            $event->addActionToSubmenu('filter', 'teams', ['url' => $this->path('admin_team', ['users[]' => $user->getId()]), 'title' => 'teams']);
        }

        if ($this->isGranted('password', $user)) {
            $forceResetToken = $this->csrfTokenManager->getToken('admin_user_force_password_reset_' . $user->getId())->getValue();
            $event->addActionToSubmenu('security', 'force_password_reset', ['url' => $this->path('admin_user_force_password_reset', ['id' => $user->getId(), 'csrfToken' => $forceResetToken]), 'title' => 'force_password_reset']);

            $revokeRememberMeToken = $this->csrfTokenManager->getToken('admin_user_revoke_remember_me_' . $user->getId())->getValue();
            $event->addActionToSubmenu('security', 'revoke_remember_me', ['url' => $this->path('admin_user_revoke_remember_me', ['id' => $user->getId(), 'csrfToken' => $revokeRememberMeToken]), 'title' => 'revoke_remember_me']);
        }

        if ($event->isIndexView() && $this->isGranted('delete', $user)) {
            $event->addDelete($this->path('admin_user_delete', ['id' => $user->getId()]));
        }
    }
}
