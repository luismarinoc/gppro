<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Doctrine\Behavior\CreatedAt;
use App\Doctrine\Behavior\CreatedTrait;
use App\Doctrine\Behavior\ModifiedAt;
use App\Doctrine\Behavior\ModifiedTrait;
use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_expenses')]
#[ORM\Index(columns: ['created_by_id'])]
#[ORM\Index(columns: ['source_expense_id'])]
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
class Expense implements CreatedAt, ModifiedAt
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public const RECURRENCE_MONTH = 'month';

    /** @var string[] */
    public const RECURRENCES = [self::RECURRENCE_MONTH];

    use CreatedTrait;
    use ModifiedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    private ?string $description = null;

    /**
     * Whole Chilean pesos (no decimals) - see design D3.
     */
    #[ORM\Column(name: 'amount', type: Types::INTEGER, nullable: false)]
    #[Assert\Range(min: 1, max: 2147483647)]
    private ?int $amount = null;

    #[ORM\Column(name: 'expense_date', type: Types::DATE_IMMUTABLE, nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $expenseDate = null;

    #[ORM\Column(name: 'recurrence', type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Choice(choices: self::RECURRENCES)]
    private ?string $recurrence = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, nullable: false, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\Choice(choices: self::STATUSES)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'required_levels', type: Types::INTEGER, nullable: true)]
    private ?int $requiredLevels = null;

    #[ORM\Column(name: 'current_level', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $currentLevel = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'source_expense_id', nullable: true, onDelete: 'SET NULL')]
    private ?Expense $sourceExpense = null;

    /**
     * Format YYYY-MM, set only on recurrence-generated copies.
     */
    #[ORM\Column(name: 'period_key', type: Types::STRING, length: 7, nullable: true)]
    private ?string $periodKey = null;

    /** @var Collection<int, ExpenseAllocation> */
    #[ORM\OneToMany(mappedBy: 'expense', targetEntity: ExpenseAllocation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, max: 20, minMessage: 'expense.allocations_minimum', maxMessage: 'expense.allocations_maximum')]
    private Collection $allocations;

    /** @var Collection<int, ExpenseApproval> */
    #[ORM\OneToMany(mappedBy: 'expense', targetEntity: ExpenseApproval::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $approvals;

    public function __construct()
    {
        $this->allocations = new ArrayCollection();
        $this->approvals = new ArrayCollection();
        $this->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->markAsModified();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): Expense
    {
        $this->description = $description;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): Expense
    {
        $this->amount = $amount;

        return $this;
    }

    public function getExpenseDate(): ?\DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(\DateTimeImmutable $expenseDate): Expense
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(?string $recurrence): Expense
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequiredLevels(): ?int
    {
        return $this->requiredLevels;
    }

    public function getCurrentLevel(): int
    {
        return $this->currentLevel;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): Expense
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getSourceExpense(): ?Expense
    {
        return $this->sourceExpense;
    }

    public function setSourceExpense(?Expense $sourceExpense): Expense
    {
        $this->sourceExpense = $sourceExpense;

        return $this;
    }

    public function getPeriodKey(): ?string
    {
        return $this->periodKey;
    }

    public function setPeriodKey(?string $periodKey): Expense
    {
        $this->periodKey = $periodKey;

        return $this;
    }

    /** @return Collection<int, ExpenseAllocation> */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(ExpenseAllocation $allocation): Expense
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setExpense($this);
        }

        return $this;
    }

    public function removeAllocation(ExpenseAllocation $allocation): Expense
    {
        if ($this->allocations->removeElement($allocation) && $allocation->getExpense() === $this) {
            $allocation->setExpense(null);
        }

        return $this;
    }

    /** @return Collection<int, ExpenseApproval> */
    public function getApprovals(): Collection
    {
        return $this->approvals;
    }

    public function isEditable(): bool
    {
        return self::STATUS_DRAFT === $this->status;
    }

    public function isApproved(): bool
    {
        return self::STATUS_APPROVED === $this->status;
    }

    /**
     * The next level awaiting clearance, or null when not pending approval.
     */
    public function nextPendingLevel(): ?int
    {
        if (self::STATUS_PENDING_APPROVAL !== $this->status) {
            return null;
        }

        return $this->currentLevel + 1;
    }

    /**
     * Computes and freezes requiredLevels for this submission - later changes
     * to the level configuration must not affect this already-submitted
     * expense (spec: "Later config change does not affect in-flight expense").
     */
    public function submitForApproval(int $requiredLevels): Expense
    {
        if (self::STATUS_DRAFT !== $this->status) {
            throw new \DomainException('Only draft expenses can be submitted for approval.');
        }

        $this->requiredLevels = $requiredLevels;
        $this->currentLevel = 0;
        $this->status = self::STATUS_PENDING_APPROVAL;

        return $this;
    }

    /**
     * Clears the given level. Levels must be cleared strictly in order.
     * Clearing the last required level moves the expense to approved.
     */
    public function clearLevel(int $level): Expense
    {
        if (self::STATUS_PENDING_APPROVAL !== $this->status) {
            throw new \DomainException('Only pending expenses can have a level cleared.');
        }

        if ($level !== $this->currentLevel + 1) {
            throw new \DomainException('Approval levels must be cleared in order.');
        }

        $this->currentLevel = $level;

        if ($this->currentLevel === $this->requiredLevels) {
            $this->status = self::STATUS_APPROVED;
        }

        return $this;
    }

    /**
     * Rejects the expense at any pending level, discarding previously
     * cleared levels (spec: "Reject discards accumulated approvals").
     */
    public function rejectApproval(): Expense
    {
        if (self::STATUS_PENDING_APPROVAL !== $this->status) {
            throw new \DomainException('Only pending expenses can be rejected.');
        }

        $this->status = self::STATUS_REJECTED;
        $this->currentLevel = 0;

        return $this;
    }
}
