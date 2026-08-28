<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\TimesheetApprovalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_timesheet_approvals')]
#[ORM\Index(columns: ['timesheet_id', 'approval_attempt'])]
#[ORM\Entity(repositoryClass: TimesheetApprovalRepository::class)]
class TimesheetApproval
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Timesheet::class, inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Timesheet $timesheet = null;
    #[ORM\Column(name: 'approval_attempt', type: Types::INTEGER, options: ['default' => 1])]
    private int $approvalAttempt = 1;
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [self::DECISION_APPROVED, self::DECISION_REJECTED])]
    private ?string $decision = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'decided_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;
    #[ORM\Column(name: 'decided_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $decidedAt = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $note = null;

    public function getId(): ?int { return $this->id; }

    public function getTimesheet(): ?Timesheet { return $this->timesheet; }

    public function setTimesheet(Timesheet $timesheet): self { $this->timesheet = $timesheet;

        return $this; }

    public function getApprovalAttempt(): int { return $this->approvalAttempt; }

    public function setApprovalAttempt(int $attempt): self { $this->approvalAttempt = $attempt;

        return $this; }

    public function getDecision(): ?string { return $this->decision; }

    public function setDecision(string $decision): self { $this->decision = $decision;

        return $this; }

    public function getDecidedBy(): ?User { return $this->decidedBy; }

    public function setDecidedBy(?User $user): self { $this->decidedBy = $user;

        return $this; }

    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    public function setDecidedAt(\DateTimeImmutable $at): self { $this->decidedAt = $at;

        return $this; }

    public function getNote(): ?string { return $this->note; }

    public function setNote(?string $note): self { $this->note = $note;

        return $this; }
}
