<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Activity\ActivityBoardService;
use App\Activity\ActivityBoardUpdateDTO;
use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use App\Entity\Project;
use App\Utils\PageSetup;
use App\Validator\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to render the per-project activity kanban board (design A4)
 * and to persist card moves/assignments made from it.
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

    /**
     * Persists a card move/assignment. Writes exclusively to
     * ActivityBoardState via ActivityBoardService::updateCard() - never to
     * Activity, Timesheet, or any billing-related table (design's central
     * non-goal invariant, see testStatusChangeDoesNotTouchBillingData()).
     *
     * The project/activity ownership check below is deliberate defense
     * against IDOR: {id} and {activity} are both plain, trivially
     * enumerable auto-increment ids, and #[IsGranted('edit', 'activity')]
     * alone only proves the caller may edit *some* activity - not that it
     * belongs to the project named in this URL.
     */
    #[Route(path: '/{id}/board/{activity}', name: 'project_board_card_update', methods: ['PATCH'])]
    #[IsGranted('edit', 'activity')]
    public function updateCardAction(Project $project, Activity $activity, Request $request, ActivityBoardService $boardService): JsonResponse
    {
        if ($activity->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Activity does not belong to this project.');
        }

        $content = $request->getContent();
        $data = [];

        if ('' !== $content) {
            try {
                $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
            }

            if (!\is_array($decoded)) {
                return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
            }

            $data = $decoded;
        }

        try {
            $dto = ActivityBoardUpdateDTO::fromArray($data);
            $state = $boardService->updateCard($activity, $dto);
        } catch (ValidationException $ex) {
            return $this->json(['message' => $ex->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serializeState($state));
    }

    /**
     * @return array{status: string, priority: ?string, dueDate: ?string, assignedTo: ?array{id: ?int, alias: ?string}}
     */
    private function serializeState(ActivityBoardState $state): array
    {
        $assignedTo = $state->getAssignedTo();

        return [
            'status' => $state->getStatus()->value,
            'priority' => $state->getPriority()?->value,
            'dueDate' => $state->getDueDate()?->format('Y-m-d'),
            'assignedTo' => null !== $assignedTo ? [
                'id' => $assignedTo->getId(),
                'alias' => $assignedTo->getAlias(),
            ] : null,
        ];
    }
}
