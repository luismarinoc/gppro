<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Activity;
use App\Entity\ActivityComment;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ActivityCommentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ActivityCommentRepository::class)]
#[Group('integration')]
class ActivityCommentRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ActivityCommentRepository
    {
        /** @var ActivityCommentRepository $repository */
        $repository = $this->getEntityManager()->getRepository(ActivityComment::class);

        return $repository;
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Activity comment test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Activity comment test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createActivity(Project $project): Activity
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName('Activity comment test activity ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        return $activity;
    }

    private function createUser(): User
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $user = new User();
        $user->setUsername('activity-comment-test-' . $suffix);
        $user->setEmail('activity-comment-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createComment(Activity $activity, User $createdBy, bool $pinned = false): ActivityComment
    {
        $comment = new ActivityComment($activity);
        $comment->setMessage('Comment ' . uniqid());
        $comment->setCreatedBy($createdBy);
        $comment->setPinned($pinned);

        $this->getRepository()->saveComment($comment);

        return $comment;
    }

    public function testGetCommentsOrdersByPinnedThenCreatedAtDescending(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project);
        $user = $this->createUser();

        $older = $this->createComment($activity, $user);
        $older->setCreatedAt(new \DateTime('-2 days'));
        $this->getRepository()->saveComment($older);

        $newer = $this->createComment($activity, $user);
        $newer->setCreatedAt(new \DateTime('-1 day'));
        $this->getRepository()->saveComment($newer);

        $pinned = $this->createComment($activity, $user, true);
        $pinned->setCreatedAt(new \DateTime('-3 days'));
        $this->getRepository()->saveComment($pinned);

        $comments = $this->getRepository()->getComments($activity);
        $ids = array_map(static fn (ActivityComment $c): ?int => $c->getId(), $comments);

        self::assertSame([$pinned->getId(), $newer->getId(), $older->getId()], $ids);
    }

    public function testGetCommentsExcludesCommentsFromOtherActivities(): void
    {
        $project = $this->createProject();
        $activityOne = $this->createActivity($project);
        $activityTwo = $this->createActivity($project);
        $user = $this->createUser();

        $ownComment = $this->createComment($activityOne, $user);
        $this->createComment($activityTwo, $user);

        $comments = $this->getRepository()->getComments($activityOne);

        self::assertCount(1, $comments);
        self::assertSame($ownComment->getId(), $comments[0]->getId());
    }

    public function testDeletingActivityCascadesItsComments(): void
    {
        $em = $this->getEntityManager();
        $project = $this->createProject();
        $activity = $this->createActivity($project);
        $user = $this->createUser();

        $comment = $this->createComment($activity, $user);
        $commentId = $comment->getId();
        self::assertNotNull($commentId);

        $em->remove($activity);
        $em->flush();
        $em->clear();

        self::assertNull($em->getRepository(ActivityComment::class)->find($commentId));
    }
}
