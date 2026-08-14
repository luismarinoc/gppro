<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Validator\Constraints as Constraints;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Table(name: 'gppro_invoice_payment_approval_levels')]
#[ORM\UniqueConstraint(name: 'UNIQ_GPPRO_INVOICE_PAYMENT_APPROVAL_LEVELS_LEVEL', columns: ['level'])]
#[ORM\Entity(repositoryClass: InvoicePaymentApprovalLevelRepository::class)]
class InvoicePaymentApprovalLevel
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'level', type: Types::INTEGER, nullable: false)]
    #[Assert\Range(min: 1)]
    private ?int $level = null;

    /**
     * D5: float, not int like Expense's whole-CLP amount - Invoice::getTotal()
     * is a real multi-currency float, casting to int would silently truncate.
     */
    #[ORM\Column(name: 'min_amount', type: Types::FLOAT, nullable: false)]
    #[Assert\Range(min: 0)]
    private ?float $minAmount = null;

    /**
     * Validated against RoleService::getAvailableNames() (mirrors D1 on
     * ExpenseApprovalLevel) - this merges security.yaml role constants with
     * custom gppro_roles rows.
     */
    #[ORM\Column(name: 'required_role', type: Types::STRING, length: 50, nullable: false)]
    #[Assert\NotBlank]
    #[Constraints\Role]
    private ?string $requiredRole = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approver_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approverUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): InvoicePaymentApprovalLevel
    {
        $this->level = $level;

        return $this;
    }

    public function getMinAmount(): ?float
    {
        return $this->minAmount;
    }

    public function setMinAmount(float $minAmount): InvoicePaymentApprovalLevel
    {
        $this->minAmount = $minAmount;

        return $this;
    }

    public function getRequiredRole(): ?string
    {
        return $this->requiredRole;
    }

    public function setRequiredRole(string $requiredRole): InvoicePaymentApprovalLevel
    {
        $this->requiredRole = $requiredRole;

        return $this;
    }

    public function getApproverUser(): ?User
    {
        return $this->approverUser;
    }

    public function setApproverUser(?User $approverUser): InvoicePaymentApprovalLevel
    {
        $this->approverUser = $approverUser;

        return $this;
    }

    /**
     * Self-contained part of the monotonic invariant (spec: "level 1 MUST
     * have minAmount = 0"). The cross-row strictly-increasing check needs
     * sibling rows and is enforced where the full set is available (form/
     * controller layer), not here.
     */
    #[Assert\Callback]
    public static function validateLevelOneMinAmount(self $level, ExecutionContextInterface $context): void
    {
        if (1 === $level->level && 0.0 !== $level->minAmount) {
            $context->buildViolation('Level 1 must have a minimum amount of 0.')
                ->atPath('minAmount')
                ->addViolation();
        }
    }
}
