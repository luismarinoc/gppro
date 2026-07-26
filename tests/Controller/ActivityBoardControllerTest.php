<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardState;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

#[Group('integration')]
class ActivityBoardControllerTest extends AbstractControllerBaseTestCase
{
    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Board controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Board controller test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    private function createActivity(Project $project, string $name): Activity
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName($name . ' ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        return $activity;
    }

    private function nameOf(Activity $activity): string
    {
        $name = $activity->getName();
        self::assertNotNull($name);

        return $name;
    }

    public function testIsSecure(): void
    {
        // no entity manager access before this call: assertUrlIsSecured()
        // boots its own kernel/client and a second boot is not supported
        // (same constraint as MilestoneInvoiceControllerTest::testIsSecure())
        $this->assertUrlIsSecured('/admin/project/1/board');
    }

    public function testIsSecureForRole(): void
    {
        // ROLE_USER has neither the full nor the team-scoped 'view_project'
        // permission (see config/packages/gppro.yaml), so it is denied
        // before a Project with id=1 needs to actually exist.
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/project/1/board');
    }

    public function testBoardActionRendersFourColumnsWithEmptyState(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $columns = $crawler->filter('.activity_board_column');
        self::assertCount(4, $columns);

        $statuses = $columns->each(static fn (Crawler $node): string => $node->attr('data-status') ?? '');
        self::assertSame(['todo', 'in_progress', 'in_review', 'done'], $statuses);

        $emptyStateNodes = $crawler->filter('.activity_board_column_empty');
        self::assertCount(4, $emptyStateNodes, 'a project with no activities must show the empty state in all 4 columns');
        self::assertSame('No activities in this column.', trim($emptyStateNodes->first()->text()));
    }

    public function testBoardActionPlacesStatelessActivityInTodoColumnAsUnassigned(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Stateless activity');

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $todoColumn = $crawler->filter('.activity_board_column[data-status="todo"]');
        self::assertCount(1, $todoColumn);
        $todoText = $todoColumn->text();

        self::assertStringContainsString($this->nameOf($activity), $todoText);
        self::assertStringContainsString('Unassigned', $todoText);

        $otherColumns = $crawler->filter('.activity_board_column[data-status="in_progress"], .activity_board_column[data-status="in_review"], .activity_board_column[data-status="done"]');
        self::assertCount(3, $otherColumns);
        $otherColumns->each(function (Crawler $node) use ($activity): void {
            self::assertStringNotContainsString($this->nameOf($activity), $node->text());
        });
    }

    public function testBoardActionShowsCardNamePriorityDueDateAndAssignee(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with full state');
        $assignee = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $dueDate = new \DateTime('2026-08-01');

        $em = $this->getEntityManager();
        $state = new ActivityBoardState();
        $state->setActivity($activity);
        $state->setPriority(ActivityBoardPriority::URGENT);
        $state->setDueDate($dueDate);
        $state->setAssignedTo($assignee);
        $em->persist($state);
        $em->flush();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $card = $crawler->filter('.activity_board_card');
        self::assertCount(1, $card, 'exactly one card must be rendered for the single activity');
        $cardText = $card->text();

        self::assertStringContainsString($this->nameOf($activity), $cardText);
        self::assertStringContainsString('Urgent', $cardText);
        self::assertStringContainsString($this->formatDate($dueDate), $cardText);
        self::assertStringContainsString($assignee->getDisplayName(), $cardText);
    }
}
