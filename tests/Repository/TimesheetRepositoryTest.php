<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\TimesheetQuery;
use App\Repository\Query\TimesheetQueryHint;
use App\Repository\TimesheetRepository;
use App\Utils\Pagination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(TimesheetRepository::class)]
#[Group('integration')]
class TimesheetRepositoryTest extends AbstractRepositoryTestCase
{
    public function testResultTypeForQueryState(): void
    {
        $em = $this->getEntityManager();
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        $query = new TimesheetQuery();

        $result = $repository->getPagerfantaForQuery($query);
        self::assertInstanceOf(Pagination::class, $result);
        self::assertFalse($query->hasQueryHint(TimesheetQueryHint::CUSTOMER_META_FIELDS));
        self::assertFalse($query->hasQueryHint(TimesheetQueryHint::PROJECT_META_FIELDS));
        self::assertFalse($query->hasQueryHint(TimesheetQueryHint::ACTIVITY_META_FIELDS));

        $result = $repository->getTimesheetsForQuery($query, true);

        self::assertTrue($query->hasQueryHint(TimesheetQueryHint::CUSTOMER_META_FIELDS));
        self::assertTrue($query->hasQueryHint(TimesheetQueryHint::PROJECT_META_FIELDS));
        self::assertTrue($query->hasQueryHint(TimesheetQueryHint::ACTIVITY_META_FIELDS));
        self::assertIsArray($result);
    }

    public function testPaginationReturnsDistinctResultsWithIdenticalBegin(): void
    {
        $em = $this->getEntityManager();
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $em->getRepository(Activity::class);
        $activity = $activityRepository->find(1);
        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository(Project::class);
        $project = $projectRepository->find(1);

        $user = $this->getUserByRole(User::ROLE_USER);
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        // all records share the exact same begin, so the default "begin DESC"
        // order alone cannot produce a stable order across paginated queries
        $begin = new \DateTime('2015-06-15 10:00:00');
        $end = new \DateTime('2015-06-15 11:00:00');
        $amount = 20;
        for ($i = 0; $i < $amount; $i++) {
            $timesheet = new Timesheet();
            $timesheet
                ->setBegin(clone $begin)
                ->setEnd(clone $end)
                ->setUser($user)
                ->setActivity($activity)
                ->setProject($project);
            $em->persist($timesheet);
        }
        $em->flush();

        $pageSize = 5;
        $collected = [];
        $pages = (int) ceil($amount / $pageSize);
        for ($page = 1; $page <= $pages; $page++) {
            $query = new TimesheetQuery();
            $query->setUser($user);
            $query->setPage($page);
            $query->setPageSize($pageSize);
            $pager = $repository->getPagerfantaForQuery($query);
            /** @var Timesheet $timesheet */
            foreach ($pager->getCurrentPageResults() as $timesheet) {
                $collected[] = $timesheet->getId();
            }
        }

        self::assertCount($amount, $collected);
        self::assertCount($amount, array_unique($collected), 'Pagination returned the same timesheet on more than one page');
    }

    public function testSave(): void
    {
        $em = $this->getEntityManager();
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $em->getRepository(Activity::class);
        $activity = $activityRepository->find(1);
        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository(Project::class);
        $project = $projectRepository->find(1);

        $user = $this->getUserByRole(User::ROLE_USER);
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);
        $timesheet = new Timesheet();
        $timesheet
            ->setBegin(new \DateTime())
            ->setEnd(new \DateTime())
            ->setDescription('foo')
            ->setUser($user)
            ->setActivity($activity)
            ->setProject($project);

        self::assertNull($timesheet->getId());
        $repository->save($timesheet);
        self::assertNotNull($timesheet->getId());
    }

    public function testSaveWithTags(): void
    {
        $em = $this->getEntityManager();
        /** @var ActivityRepository $activityRepository */
        $activityRepository = $em->getRepository(Activity::class);
        $activity = $activityRepository->find(1);
        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository(Project::class);
        $project = $projectRepository->find(1);

        $user = $this->getUserByRole(User::ROLE_USER);
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);
        $tagOne = new Tag();
        $tagOne->setName('Travel');
        $tagTwo = new Tag();
        $tagTwo->setName('Picture');
        $timesheet = new Timesheet();
        $timesheet
            ->setBegin(new \DateTime())
            ->setEnd(new \DateTime())
            ->setDescription('foo')
            ->setUser($user)
            ->setActivity($activity)
            ->setProject($project)
            ->addTag($tagOne)
            ->addTag($tagTwo);

        self::assertNull($timesheet->getId());
        $repository->save($timesheet);
        self::assertNotNull($timesheet->getId());
        self::assertEquals(2, $timesheet->getTags()->count());
        self::assertEquals('Travel', $timesheet->getTags()->get(0)->getName());
        self::assertNotNull($timesheet->getTags()->get(0)->getId());
        self::assertEquals('Picture', $timesheet->getTags()->get(1)->getName());
        self::assertNotNull($timesheet->getTags()->get(1)->getId());
    }

    private function createCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Timesheet repository test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createProject(Customer $customer, string $name = 'Timesheet repository test project'): Project
    {
        $em = $this->getEntityManager();

        $project = new Project();
        $project->setName($name . ' ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createInvoice(Customer $customer): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_NEW);
        $invoice->setTotal(100.0);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('ts-repo-' . uniqid());
        $invoice->setFilename('ts-repo-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setUser($this->getUserByRole(User::ROLE_USER));
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    private function createTimesheet(Project $project, ?Invoice $invoice, float $rate, int $duration): Timesheet
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName('Timesheet repository test activity ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        // aligned on a full minute boundary: the default rounding rules round
        // "begin" down and "end" up to the full minute, which would otherwise
        // shift the recorded duration away from the value asserted in tests
        $begin = new \DateTime('-1 year');
        $begin->setTime((int) $begin->format('H'), (int) $begin->format('i'), 0);
        $end = (clone $begin)->modify('+' . $duration . ' seconds');

        $timesheet = new Timesheet();
        $timesheet
            ->setBegin($begin)
            ->setEnd($end)
            ->setUser($this->getUserByRole(User::ROLE_USER))
            ->setActivity($activity)
            ->setProject($project)
            ->setFixedRate($rate);
        $timesheet->setInvoice($invoice);
        $em->persist($timesheet);
        $em->flush();

        return $timesheet;
    }

    public function testGetProjectSubtotalsByInvoiceIdsReturnsEmptyArrayForEmptyInput(): void
    {
        $em = $this->getEntityManager();
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        self::assertSame([], $repository->getProjectSubtotalsByInvoiceIds([]));
    }

    public function testGetProjectSubtotalsByInvoiceIdsGroupsByInvoiceAndProject(): void
    {
        $em = $this->getEntityManager();
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        $customer = $this->createCustomer();
        $projectOne = $this->createProject($customer, 'Project one');
        $projectTwo = $this->createProject($customer, 'Project two');

        $invoiceMulti = $this->createInvoice($customer);
        $invoiceOther = $this->createInvoice($customer);

        // same invoice, same project: must be summed into one row
        $this->createTimesheet($projectOne, $invoiceMulti, 100.0, 3600);
        $this->createTimesheet($projectOne, $invoiceMulti, 50.0, 1800);

        // same invoice, different project: must be a separate row
        $this->createTimesheet($projectTwo, $invoiceMulti, 25.0, 900);

        // timesheet from another invoice: must be excluded entirely
        $this->createTimesheet($projectOne, $invoiceOther, 999.0, 1);

        // timesheet with no invoice at all: must be excluded entirely
        $this->createTimesheet($projectOne, null, 999.0, 1);

        $invoiceMultiId = $invoiceMulti->getId();
        self::assertNotNull($invoiceMultiId);

        $rows = $repository->getProjectSubtotalsByInvoiceIds([$invoiceMultiId]);

        self::assertCount(2, $rows);

        $byProject = [];
        foreach ($rows as $row) {
            self::assertSame($invoiceMultiId, $row['invoiceId']);
            $byProject[$row['projectId']] = $row;
        }

        $projectOneId = $projectOne->getId();
        $projectTwoId = $projectTwo->getId();
        self::assertNotNull($projectOneId);
        self::assertNotNull($projectTwoId);

        self::assertArrayHasKey($projectOneId, $byProject);
        self::assertSame(150.0, $byProject[$projectOneId]['amount']);
        self::assertSame(5400, $byProject[$projectOneId]['duration']);
        self::assertSame($projectOne->getName(), $byProject[$projectOneId]['projectName']);

        self::assertArrayHasKey($projectTwoId, $byProject);
        self::assertSame(25.0, $byProject[$projectTwoId]['amount']);
        self::assertSame(900, $byProject[$projectTwoId]['duration']);
        self::assertSame($projectTwo->getName(), $byProject[$projectTwoId]['projectName']);
    }

    private function createTeam(): Team
    {
        $em = $this->getEntityManager();
        $team = new Team('Dashboard repo test team ' . uniqid());
        $em->persist($team);
        $em->flush();

        return $team;
    }

    private function createProjectWithTeam(Team $team): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Dashboard repo test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');

        $project = new Project();
        $project->setName('Dashboard repo test project ' . uniqid());
        $project->setCustomer($customer);
        $project->addTeam($team);

        $em->persist($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function addUserToTeamAsLead(User $user, Team $team): void
    {
        $member = new TeamMember();
        $member->setTeam($team);
        $member->setUser($user);
        $member->setTeamlead(true);
        $this->getEntityManager()->persist($member);
        $this->getEntityManager()->flush();
    }

    private function addUserToTeam(User $user, Team $team): void
    {
        $member = new TeamMember();
        $member->setTeam($team);
        $member->setUser($user);
        $this->getEntityManager()->persist($member);
        $this->getEntityManager()->flush();
    }

    private function createTimesheetEntry(User $owner, Project $project): Timesheet
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName('Dashboard repo test activity ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        $timesheet = new Timesheet();
        $timesheet->setUser($owner);
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setBegin(new \DateTime('-2 hours'));
        $timesheet->setEnd(new \DateTime('-1 hours'));
        $em->persist($timesheet);
        $em->flush();

        return $timesheet;
    }

    /**
     * Task 4.1: only entries belonging to a project whose team the given
     * user leads are returned; entries under a team the user is a member of
     * (but not lead) or unrelated entries must not appear.
     */
    public function testFindPendingApprovalForUserReturnsOnlyEntriesLedByUser(): void
    {
        $em = $this->getEntityManager();
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        $lead = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $member = $this->getUserByRole(User::ROLE_USER);

        $ledTeam = $this->createTeam();
        $this->addUserToTeamAsLead($lead, $ledTeam);
        $ledProject = $this->createProjectWithTeam($ledTeam);
        $ledEntry = $this->createTimesheetEntry($member, $ledProject);

        $otherTeam = $this->createTeam();
        $this->addUserToTeam($lead, $otherTeam);
        $otherProject = $this->createProjectWithTeam($otherTeam);
        $this->createTimesheetEntry($member, $otherProject);

        $result = $repository->findPendingApprovalForUser($lead);
        $ids = array_map(static fn (Timesheet $t) => $t->getId(), $result);

        self::assertCount(1, $result);
        self::assertSame([$ledEntry->getId()], $ids);
    }

    /**
     * Task 4.1: an entry that already carries an approval must not be
     * returned again, even for the lead who could approve it.
     */
    public function testFindPendingApprovalForUserExcludesAlreadyApprovedEntries(): void
    {
        $em = $this->getEntityManager();
        /** @var TimesheetRepository $repository */
        $repository = $em->getRepository(Timesheet::class);

        $lead = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $member = $this->getUserByRole(User::ROLE_USER);

        $team = $this->createTeam();
        $this->addUserToTeamAsLead($lead, $team);
        $project = $this->createProjectWithTeam($team);

        $pendingEntry = $this->createTimesheetEntry($member, $project);
        $approvedEntry = $this->createTimesheetEntry($member, $project);
        $approvedEntry->approve($lead);
        $em->persist($approvedEntry);
        $em->flush();

        $result = $repository->findPendingApprovalForUser($lead);
        $ids = array_map(static fn (Timesheet $t) => $t->getId(), $result);

        self::assertSame([$pendingEntry->getId()], $ids);
    }
}
