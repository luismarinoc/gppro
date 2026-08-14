<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityComment;
use App\Entity\Project;
use App\Form\ActivityCommentForm;
use App\Repository\ActivityCommentRepository;
use App\Repository\ActivityRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\VisibilityInterface;
use App\Utils\PageSetup;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to render the project-scoped activity workspace (design D1):
 * a single screen composing the project's activity list (panel 1), the
 * selected activity's base detail (panel 2), and its comment thread
 * (panel 3). Additive only - never reads or writes ActivityBoardState
 * (proposal A7), and never touches the Kanban board (Rule 9).
 */
#[Route(path: '/admin/project')]
final class ActivityWorkspaceController extends AbstractController
{
    /**
     * Sized for the primary navigation column of this screen, not for the
     * details-page card (5) nor the default query page size (50) - see
     * design D4.
     */
    private const PAGE_SIZE = 25;

    #[Route(path: '/{id}/workspace/{activity}', defaults: ['activity' => null], requirements: ['activity' => '\d+'], name: 'project_activity_workspace', methods: ['GET'])]
    #[IsGranted('view', 'project')]
    public function indexAction(Project $project, ?Activity $activity, Request $request, ActivityRepository $activityRepository, ActivityCommentRepository $commentRepository): Response
    {
        // defense against IDOR (design's 404 guard, copied from
        // ActivityBoardController::updateCardAction): {id} and {activity}
        // are both plain, trivially enumerable auto-increment ids, and
        // IsGranted('view', 'project') alone only proves the caller may view
        // *this* project - not that the activity belongs to it.
        if ($activity !== null && $activity->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Activity does not belong to this project.');
        }

        $page = $request->query->getInt('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $query = new ActivityQuery();
        $query->setCurrentUser($this->getUser());
        $query->setPage($page);
        $query->setPageSize(self::PAGE_SIZE);
        $query->addProject($project);
        $query->setExcludeGlobals(true);
        $query->setVisibility(VisibilityInterface::SHOW_BOTH);
        $query->addOrderGroup('visible', ActivityQuery::ORDER_DESC);
        $query->addOrderGroup('name', ActivityQuery::ORDER_ASC);

        $entries = $activityRepository->getPagerfantaForQuery($query);

        $comments = null;
        $commentForm = null;

        if ($activity !== null && $this->isGranted('comments', $project)) {
            $comments = $commentRepository->getComments($activity);
            $commentForm = $this->getCommentForm(new ActivityComment($activity))->createView();
        }

        return $this->render('activity_workspace/index.html.twig', [
            'page_setup' => new PageSetup('activity_workspace.title'),
            'project' => $project,
            'activities' => $entries,
            'activity' => $activity,
            'comments' => $comments,
            'commentForm' => $commentForm,
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    #[Route(path: '/{id}/workspace/{activity}/comment_add', requirements: ['activity' => '\d+'], name: 'project_activity_workspace_comment_add', methods: ['POST'])]
    #[IsGranted('comments', 'project')]
    public function addCommentAction(Project $project, Activity $activity, Request $request, ActivityCommentRepository $commentRepository): Response
    {
        if ($activity->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Activity does not belong to this project.');
        }

        $comment = new ActivityComment($activity);
        $form = $this->getCommentForm($comment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $commentRepository->saveComment($comment);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->redirectToRoute('project_activity_workspace', ['id' => $project->getId(), 'activity' => $activity->getId()]);
    }

    private function getCommentForm(ActivityComment $comment): FormInterface
    {
        if (null === $comment->getId()) {
            $comment->setCreatedBy($this->getUser());
        }

        $activity = $comment->getActivity();
        $project = $activity->getProject();

        return $this->createForm(ActivityCommentForm::class, $comment, [
            'action' => $this->generateUrl('project_activity_workspace_comment_add', [
                'id' => $project?->getId(),
                'activity' => $activity->getId(),
            ]),
            'method' => 'POST',
        ]);
    }
}
