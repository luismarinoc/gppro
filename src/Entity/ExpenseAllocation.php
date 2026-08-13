<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_expense_allocations')]
#[ORM\Index(columns: ['expense_id'])]
#[ORM\Index(columns: ['project_id'])]
#[ORM\Entity]
class ExpenseAllocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'allocations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Expense $expense = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\Column(name: 'percentage', type: Types::DECIMAL, precision: 5, scale: 2, nullable: false)]
    #[Assert\Range(min: 0.01, max: 100)]
    private ?string $percentage = null;

    /**
     * Derived CLP split of the parent expense amount - computed by
     * AllocationSplitter (Phase 2), stored here for persistence.
     */
    #[ORM\Column(name: 'amount_clp', type: Types::INTEGER, nullable: true)]
    private ?int $amountClp = null;

    #[ORM\Column(name: 'charged', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $charged = false;

    #[ORM\OneToOne(targetEntity: QuotationLine::class)]
    #[ORM\JoinColumn(name: 'quotation_line_id', nullable: true, onDelete: 'SET NULL', unique: true)]
    private ?QuotationLine $quotationLine = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpense(): ?Expense
    {
        return $this->expense;
    }

    public function setExpense(?Expense $expense): ExpenseAllocation
    {
        $this->expense = $expense;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): ExpenseAllocation
    {
        $this->project = $project;

        return $this;
    }

    public function getPercentage(): ?string
    {
        return $this->percentage;
    }

    public function setPercentage(string $percentage): ExpenseAllocation
    {
        $this->percentage = $percentage;

        return $this;
    }

    public function getAmountClp(): ?int
    {
        return $this->amountClp;
    }

    public function setAmountClp(?int $amountClp): ExpenseAllocation
    {
        $this->amountClp = $amountClp;

        return $this;
    }

    public function isCharged(): bool
    {
        return $this->charged;
    }

    public function getQuotationLine(): ?QuotationLine
    {
        return $this->quotationLine;
    }

    /**
     * Marks this allocation as cross-charged onto a quotation line.
     * A given allocation may only be charged once (spec: "Double charge is blocked").
     */
    public function markCharged(QuotationLine $quotationLine): ExpenseAllocation
    {
        if ($this->charged) {
            throw new \DomainException('This allocation has already been charged.');
        }

        $this->quotationLine = $quotationLine;
        $this->charged = true;

        return $this;
    }
}
