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
use App\Repository\QuotationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_quotations')]
#[ORM\Index(columns: ['customer_id'])]
#[ORM\Entity(repositoryClass: QuotationRepository::class)]
class Quotation implements CreatedAt, ModifiedAt
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_INVOICED = 'invoiced';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_INVOICED,
    ];

    use CreatedTrait;
    use ModifiedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\OneToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: true, onDelete: 'SET NULL', unique: true)]
    private ?Invoice $invoice = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, nullable: false, options: ['default' => self::STATUS_DRAFT])]
    #[Assert\Choice(choices: self::STATUSES)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'valid_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(name: 'tax', type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $tax = null;

    #[ORM\Column(name: 'discount', type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $discount = null;

    #[ORM\Column(name: 'surcharge', type: Types::DECIMAL, precision: 18, scale: 4, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $surcharge = null;

    /** @var Collection<int, QuotationLine> */
    #[ORM\OneToMany(mappedBy: 'quotation', targetEntity: QuotationLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, max: 10, minMessage: 'quotation.lines_minimum', maxMessage: 'quotation.lines_maximum')]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->markAsModified();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): Quotation
    {
        $this->customer = $customer;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): Quotation
    {
        $this->project = $project;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): Quotation
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function markAsInvoiced(Invoice $invoice): Quotation
    {
        if ($this->status !== self::STATUS_ACCEPTED || $this->invoice !== null) {
            throw new \DomainException('Only accepted quotations can be converted, and each quotation can be invoiced once.');
        }

        $this->invoice = $invoice;
        $this->status = self::STATUS_INVOICED;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    private function setStatus(string $status): Quotation
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown quotation status');
        }
        $this->status = $status;

        return $this;
    }

    public function markAsSent(): Quotation
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('Only draft quotations can be sent.');
        }

        return $this->setStatus(self::STATUS_SENT);
    }

    public function accept(): Quotation
    {
        if ($this->status !== self::STATUS_SENT) {
            throw new \DomainException('Only sent quotations can be accepted.');
        }

        return $this->setStatus(self::STATUS_ACCEPTED);
    }

    public function reject(): Quotation
    {
        if ($this->status !== self::STATUS_SENT) {
            throw new \DomainException('Only sent quotations can be rejected.');
        }

        return $this->setStatus(self::STATUS_REJECTED);
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): Quotation
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getTax(): ?string
    {
        return $this->tax;
    }

    public function setTax(?string $tax): Quotation
    {
        $this->tax = $tax;

        return $this;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(?string $discount): Quotation
    {
        $this->discount = $discount;

        return $this;
    }

    public function getSurcharge(): ?string
    {
        return $this->surcharge;
    }

    public function setSurcharge(?string $surcharge): Quotation
    {
        $this->surcharge = $surcharge;

        return $this;
    }

    /** @return Collection<int, QuotationLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(QuotationLine $line): Quotation
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuotation($this);
        }

        return $this;
    }

    public function removeLine(QuotationLine $line): Quotation
    {
        if ($this->lines->removeElement($line) && $line->getQuotation() === $this) {
            $line->setQuotation(null);
        }

        return $this;
    }
}
