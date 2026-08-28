<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Timesheet;

use App\Entity\Timesheet;
use App\Entity\TimesheetApproval;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class TimesheetApprovalService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function submit(Timesheet $timesheet): Timesheet
    {
        $this->transaction(function () use ($timesheet): void {
            $timesheet->submitForApproval();
            $this->entityManager->persist($timesheet);
            $this->entityManager->flush();
        });

        return $timesheet;
    }

    public function approve(Timesheet $timesheet, User $actor, ?string $note = null): Timesheet
    {
        return $this->decide($timesheet, $actor, TimesheetApproval::DECISION_APPROVED, $note);
    }

    public function reject(Timesheet $timesheet, User $actor, ?string $note = null): Timesheet
    {
        return $this->decide($timesheet, $actor, TimesheetApproval::DECISION_REJECTED, $note);
    }

    private function decide(Timesheet $timesheet, User $actor, string $decision, ?string $note): Timesheet
    {
        $this->transaction(function () use ($timesheet, $actor, $decision, $note): void {
            // Subject authorization is performed by TimesheetVoter at the HTTP
            // boundary. Do not repeat it against a separately hydrated graph.
            if (!$timesheet->isPendingApproval()) {
                throw new \DomainException('Only pending timesheets can receive an approval decision.');
            }
            $approval = (new TimesheetApproval())
                ->setTimesheet($timesheet)
                ->setApprovalAttempt($timesheet->getApprovalAttempt())
                ->setDecision($decision)
                ->setDecidedBy($actor)
                ->setDecidedAt(new \DateTimeImmutable())
                ->setNote($note);
            if ($decision === TimesheetApproval::DECISION_APPROVED) {
                $timesheet->approve($actor);
            } else {
                $timesheet->rejectApproval();
            }
            $this->entityManager->persist($approval);
            $this->entityManager->persist($timesheet);
            $this->entityManager->flush();
        });

        return $timesheet;
    }

    /** @param callable(): void $operation */
    private function transaction(callable $operation): void
    {
        $this->entityManager->beginTransaction();
        try {
            $operation();
            $this->entityManager->commit();
        } catch (\Throwable $exception) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $exception;
        }
    }
}
