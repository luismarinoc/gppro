<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Activity\ActivityBoardService;
use App\Entity\Project;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to render the per-project activity kanban board (design A4).
 * Read-only in this PR - the move/assign PATCH endpoint is added in a later
 * PR of this feature.
 */
#[Route(path: '/admin/project')]
final class ActivityBoardController extends AbstractController
{
    #[Route(path: '/{id}/board', name: 'project_board', methods: ['GET'])]
    #[IsGranted('view', 'project')]
    public function boardAction(Project $project, ActivityBoardService $boardService): Response
    {
        $columns = $boardService->createBoard($project, $this->getUser());

        $page = new PageSetup('activity_board.title');

        return $this->render('project/board.html.twig', [
            'page_setup' => $page,
            'project' => $project,
            'columns' => $columns,
        ]);
    }
}
