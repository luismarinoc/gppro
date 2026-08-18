<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/** @extends EntityRepository<Expense> */
class ExpenseRepository extends EntityRepository
{
    public function saveExpense(Expense $expense): void
    {
        $this->getEntityManager()->persist($expense);
        $this->getEntityManager()->flush();
    }

    public function deleteExpense(Expense $expense): void
    {
        $this->getEntityManager()->remove($expense);
        $this->getEntityManager()->flush();
    }

    /**
     * Design D3: visible iff a team-accessible allocation.project exists
     * (mirrors TimesheetRepository's private SIZE()=0/isMemberOf() block,
     * duplicated here rather than shared since QB criteria are alias-bound)
     * OR the user created the expense; admins bypass via canSeeAllData().
     * distinct() guards against duplicate rows when several allocations of
     * the same expense match the team join.
     *
     * @return Expense[]
     */
    public function findForListing(User $user, ?string $status = null): array
    {
        $query = $this->createQueryBuilder('e')
            ->leftJoin('e.allocations', 'ea')
            ->leftJoin('ea.project', 'p')
            ->leftJoin('p.customer', 'c')
            ->distinct()
            ->orderBy('e.createdAt', 'DESC');

        if (null !== $status && \in_array($status, Expense::STATUSES, true)) {
            $query->andWhere('e.status = :status')->setParameter('status', $status);
        }

        if (!$user->canSeeAllData()) {
            $this->addVisibilityCriteria($query, $user);
        }

        return $query->getQuery()->getResult();
    }

    private function addVisibilityCriteria(QueryBuilder $qb, User $user): void
    {
        $teamIds = array_values(array_unique(array_map(
            static fn (Team $team): ?int => $team->getId(),
            $user->getTeams()
        )));

        $creatorMatch = 'e.createdBy = :user';

        if (empty($teamIds)) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->andX('SIZE(p.teams) = 0', 'SIZE(c.teams) = 0'),
                $creatorMatch
            ));
            $qb->setParameter('user', $user);

            return;
        }

        $teamMatch = $qb->expr()->andX(
            $qb->expr()->orX('SIZE(p.teams) = 0', $qb->expr()->isMemberOf(':teams', 'p.teams')),
            $qb->expr()->orX('SIZE(c.teams) = 0', $qb->expr()->isMemberOf(':teams', 'c.teams'))
        );

        $qb->andWhere($qb->expr()->orX($teamMatch, $creatorMatch));
        $qb->setParameter('teams', $teamIds);
        $qb->setParameter('user', $user);
    }

    /**
     * Expenses pending approval that were not created by the given user
     * (spec: "The expense creator MUST NOT approve any of its own levels").
     * Per-level role matching is enforced by ExpenseApprovalPolicy, not here.
     *
     * @return Expense[]
     */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.createdBy != :user OR e.createdBy IS NULL')
            ->setParameter('status', Expense::STATUS_PENDING_APPROVAL)
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Approvals bell badge (design A5): COUNT()-only sibling of
     * findPendingForUser(), same naive creator-exclusion scope, no entity
     * hydration - must not call/wrap that method (D8).
     */
    public function countPendingForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.status = :status')
            ->andWhere('e.createdBy != :user OR e.createdBy IS NULL')
            ->setParameter('status', Expense::STATUS_PENDING_APPROVAL)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Original recurring expenses eligible to generate a new period copy.
     * Excludes generated copies themselves (sourceExpense IS NOT NULL) so
     * the recurrence chain never regenerates from a regenerated expense.
     *
     * @return Expense[]
     */
    public function findRecurringSources(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.recurrence = :recurrence')
            ->andWhere('e.sourceExpense IS NULL')
            ->setParameter('recurrence', Expense::RECURRENCE_MONTH)
            ->getQuery()
            ->getResult();
    }

    /**
     * Pre-check used by RecurringExpenseGenerator before inserting a new
     * copy - the DB-level unique index (source_expense_id, period_key) is
     * the final idempotency guarantee under concurrency (design D6/File
     * Changes: "DB-level idempotency for the cron, safe under concurrency").
     */
    public function findGeneratedCopy(Expense $source, string $periodKey): ?Expense
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.sourceExpense = :source')
            ->andWhere('e.periodKey = :periodKey')
            ->setParameter('source', $source)
            ->setParameter('periodKey', $periodKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Read-only identification of non-CLP expenses that went through
     * approval-level resolution, allocation split, or cross-charge before
     * the currency-normalization fix (design/spec: "Identify historical
     * expenses processed under the raw-amount assumption"). Never
     * auto-corrects - callers must scope any retroactive fix separately.
     *
     * @return Expense[]
     */
    public function findNonClpProcessedBeforeNormalization(): array
    {
        $qb = $this->createQueryBuilder('e');

        return $qb
            ->leftJoin('e.allocations', 'ea')
            ->distinct()
            ->andWhere('e.currency != :clp')
            ->andWhere($qb->expr()->orX(
                'e.requiredLevels IS NOT NULL',
                'ea.amountClp IS NOT NULL',
                'ea.charged = true'
            ))
            ->setParameter('clp', Expense::CURRENCY_CLP)
            ->getQuery()
            ->getResult();
    }
}
