<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\ExpenseApprovalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_expense_approvals')]
#[ORM\UniqueConstraint(name: 'UNIQ_GPPRO_EXPENSE_APPROVALS_EXPENSE_LEVEL', columns: ['expense_id', 'level'])]
#[ORM\Index(columns: ['approved_by_id'])]
#[ORM\Entity(repositoryClass: ExpenseApprovalRepository::class)]
class ExpenseApproval
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    /** @var string[] */
    public const DECISIONS = [self::DECISION_APPROVED, self::DECISION_REJECTED];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Expense $expense = null;

    #[ORM\Column(name: 'level', type: Types::INTEGER, nullable: false)]
    #[Assert\Range(min: 1)]
    private ?int $level = null;

    #[ORM\Column(name: 'decision', type: Types::STRING, length: 20, nullable: false)]
    #[Assert\Choice(choices: self::DECISIONS)]
    private ?string $decision = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(Expense $expense): ExpenseApproval
    {
        $this->expense = $expense;

        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): ExpenseApproval
    {
        $this->level = $level;

        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(string $decision): ExpenseApproval
    {
        $this->decision = $decision;

        return $this;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): ExpenseApproval
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(\DateTimeImmutable $approvedAt): ExpenseApproval
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): ExpenseApproval
    {
        $this->note = $note;

        return $this;
    }
}
