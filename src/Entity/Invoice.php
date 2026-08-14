<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Export\Annotation as Exporter;
use App\Invoice\InvoiceModel;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_invoices')]
#[ORM\UniqueConstraint(columns: ['invoice_number'])]
#[ORM\UniqueConstraint(columns: ['invoice_filename'])]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[UniqueEntity('invoiceNumber')]
#[UniqueEntity('invoiceFilename')]
#[Serializer\ExclusionPolicy('all')]
#[Exporter\Order(['id', 'createdAt', 'invoiceNumber', 'status', 'customer', 'subtotal', 'total', 'tax', 'currency', 'vat', 'dueDays', 'dueDate', 'paymentDate', 'user', 'invoiceFilename', 'customerNumber', 'comment'])]
#[Exporter\Expose(name: 'customer', label: 'customer', exp: 'object.getCustomer() === null ? null : object.getCustomer().getName()')]
#[Exporter\Expose(name: 'customerNumber', label: 'number', exp: 'object.getCustomer() === null ? null : object.getCustomer().getNumber()')]
#[Exporter\Expose(name: 'dueDate', label: 'invoice.due_days', type: 'datetime', exp: 'object.getDueDate() === null ? null : object.getDueDate()')]
#[Exporter\Expose(name: 'user', label: 'username', type: 'string', exp: 'object.getUser() === null ? null : object.getUser().getDisplayName()')]
#[Exporter\Expose(name: 'paymentDate', label: 'invoice.payment_date', type: 'date', exp: 'object.getPaymentDate() === null ? null : object.getPaymentDate()')]
class Invoice implements EntityWithMetaFields
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_NEW = 'new';

    public const PAYMENT_APPROVAL_PENDING = 'pending';
    public const PAYMENT_APPROVAL_APPROVED = 'approved';
    public const PAYMENT_APPROVAL_REJECTED = 'rejected';

    /**
     * Unique invoice ID
     */
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'id', type: 'integer')]
    private ?int $id = null;
    #[ORM\Column(name: 'invoice_number', type: Types::STRING, length: 50, nullable: false)]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'invoice.number', type: 'string')]
    private ?string $invoiceNumber = null;
    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'comment')]
    private ?string $comment = null;
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[OA\Property(ref: '#/components/schemas/Customer')]
    private ?Customer $customer = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[OA\Property(ref: '#/components/schemas/User')]
    private ?User $user = null;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Assert\NotNull]
    private ?\DateTime $createdAt = null;
    #[ORM\Column(name: 'timezone', type: Types::STRING, length: 64, nullable: false)]
    private ?string $timezone = null;
    #[ORM\Column(name: 'total', type: Types::FLOAT, nullable: false)]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'total_rate', type: 'float')]
    private float $total = 0.00;
    #[ORM\Column(name: 'tax', type: Types::FLOAT, nullable: false)]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'invoice.tax', type: 'float')]
    private float $tax = 0.00;
    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Length(max: 3)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'currency', type: 'string')]
    private ?string $currency = null;
    #[ORM\Column(name: 'due_days', type: Types::INTEGER, length: 3, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 999)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'due_days', type: 'integer')]
    private int $dueDays = 30;
    #[ORM\Column(name: 'vat', type: Types::FLOAT, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Range(min: 0.0, max: 99.99)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'tax_rate', type: 'float')]
    private float $vat = 0.00;
    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, nullable: false)]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'status', type: 'string')]
    private string $status = self::STATUS_NEW;
    #[ORM\Column(name: 'invoice_filename', type: Types::STRING, length: 150, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Length(min: 1, max: 150)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(label: 'file', type: 'string')]
    private ?string $invoiceFilename = null;
    private bool $localized = false;
    #[ORM\Column(name: 'payment_date', type: 'date', nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?\DateTime $paymentDate = null;
    /**
     * Payment-approval state (D4, orthogonal to the business `status` above,
     * hence the distinct `paymentApproval*` prefix). Null = not yet
     * submitted for payment approval (including all grandfathered
     * historical PAID invoices, decision 8).
     */
    #[ORM\Column(name: 'payment_approval_status', type: Types::STRING, length: 20, nullable: true)]
    private ?string $paymentApprovalStatus = null;
    #[ORM\Column(name: 'payment_required_levels', type: Types::INTEGER, nullable: true)]
    private ?int $paymentRequiredLevels = null;
    #[ORM\Column(name: 'payment_current_level', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $paymentCurrentLevel = 0;
    /**
     * Meta fields registered with the invoice
     *
     * @var Collection<InvoiceMeta>
     */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceMeta::class, cascade: ['persist'])]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    #[Serializer\Type(name: 'array<App\Entity\InvoiceMeta>')]
    #[Serializer\SerializedName('metaFields')]
    #[Serializer\Accessor(getter: 'getVisibleMetaFields')]
    private Collection $meta;

    public function __construct()
    {
        $this->meta = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('createdAt')]
    #[Serializer\Groups(['Default'])]
    #[Exporter\Expose(name: 'createdAt', label: 'date', type: 'datetime')]
    public function getCreatedAt(): ?\DateTime
    {
        if (!$this->localized) {
            if (null !== $this->createdAt && null !== $this->timezone) {
                $this->createdAt->setTimezone(new \DateTimeZone($this->timezone));
            }

            $this->localized = true;
        }

        return $this->createdAt;
    }

    public function getDueDate(): ?\DateTime
    {
        if (null === $this->getCreatedAt()) {
            return null;
        }

        $dueDate = clone $this->getCreatedAt();
        $dueDate->modify('+ ' . $this->dueDays . 'days');

        return $dueDate;
    }

    #[Serializer\VirtualProperty()]
    #[Serializer\SerializedName('overdue')]
    #[Serializer\Groups(['Default'])]
    public function isOverdue(): bool
    {
        if (null === $this->getDueDate()) {
            return false;
        }

        return $this->getDueDate()->getTimestamp() < (new \DateTime('now', new \DateTimeZone($this->timezone)))->getTimestamp();
    }

    public function setFilename(string $filename): Invoice
    {
        $this->invoiceFilename = $filename;

        return $this;
    }

    public function setModel(InvoiceModel $model): Invoice
    {
        $template = $model->getTemplate();

        if ($template->getDueDays() === null || $template->getVat() === null) {
            throw new \InvalidArgumentException('Missing due-days or vat setting');
        }

        $customer = $model->getCustomer();

        $user = $model->getUser();
        if ($user === null) {
            throw new \InvalidArgumentException('Missing invoice user');
        }

        $this->setCustomer($customer);
        $this->setUser($user);
        $this->setTotal($model->getCalculator()->getTotal());
        $this->setTax($model->getCalculator()->getTax());
        $this->setInvoiceNumber($model->getInvoiceNumber());
        $this->setCurrency($model->getCurrency());
        $this->setCreatedAt($model->getInvoiceDate());
        $this->setDueDays($template->getDueDays());
        $this->setVat($template->getVat());

        return $this;
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function setIsNew(): Invoice
    {
        $this->setPaymentDate(null);
        $this->status = self::STATUS_NEW;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function setIsPending(): Invoice
    {
        $this->setPaymentDate(null);
        $this->status = self::STATUS_PENDING;

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function setIsPaid(): Invoice
    {
        $this->status = self::STATUS_PAID;

        return $this;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        if (!\in_array($status, [self::STATUS_NEW, self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELED])) {
            throw new \InvalidArgumentException('Unknown invoice status');
        }

        $this->status = $status;
    }

    public function setIsCanceled(): void
    {
        $this->status = self::STATUS_CANCELED;
    }

    public function getDueDays(): int
    {
        return $this->dueDays;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getInvoiceFilename(): ?string
    {
        return $this->invoiceFilename;
    }

    #[Exporter\Expose(label: 'invoice.subtotal', type: 'float', name: 'subtotal')]
    public function getSubtotal(): float
    {
        return $this->total - $this->tax;
    }

    public function getPaymentDate(): ?\DateTime
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(?\DateTime $paymentDate): Invoice
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getPaymentApprovalStatus(): ?string
    {
        return $this->paymentApprovalStatus;
    }

    public function getPaymentRequiredLevels(): ?int
    {
        return $this->paymentRequiredLevels;
    }

    public function getPaymentCurrentLevel(): int
    {
        return $this->paymentCurrentLevel;
    }

    public function isPaymentApproved(): bool
    {
        return self::PAYMENT_APPROVAL_APPROVED === $this->paymentApprovalStatus;
    }

    /**
     * The next level awaiting clearance, or null when not pending payment approval.
     */
    public function nextPendingPaymentLevel(): ?int
    {
        if (self::PAYMENT_APPROVAL_PENDING !== $this->paymentApprovalStatus) {
            return null;
        }

        return $this->paymentCurrentLevel + 1;
    }

    /**
     * Computes and freezes paymentRequiredLevels for this submission (decision
     * 5 of the proposal) - later changes to the invoice's amount or to the
     * level configuration must not affect this already-submitted invoice.
     */
    public function submitForPaymentApproval(int $requiredLevels): Invoice
    {
        if (null !== $this->paymentApprovalStatus) {
            throw new \DomainException('This invoice was already submitted for payment approval.');
        }

        $this->paymentRequiredLevels = $requiredLevels;
        $this->paymentCurrentLevel = 0;
        $this->paymentApprovalStatus = self::PAYMENT_APPROVAL_PENDING;

        return $this;
    }

    /**
     * Clears the given level. Levels must be cleared strictly in order.
     * Clearing the last required level makes the invoice eligible for PAID.
     */
    public function clearPaymentLevel(int $level): Invoice
    {
        if (self::PAYMENT_APPROVAL_PENDING !== $this->paymentApprovalStatus) {
            throw new \DomainException('Only invoices pending payment approval can have a level cleared.');
        }

        if ($level !== $this->paymentCurrentLevel + 1) {
            throw new \DomainException('Payment approval levels must be cleared in order.');
        }

        $this->paymentCurrentLevel = $level;

        if ($this->paymentCurrentLevel === $this->paymentRequiredLevels) {
            $this->paymentApprovalStatus = self::PAYMENT_APPROVAL_APPROVED;
        }

        return $this;
    }

    /**
     * Rejects the payment approval at any pending level, discarding
     * previously cleared levels.
     */
    public function rejectPaymentApproval(): Invoice
    {
        if (self::PAYMENT_APPROVAL_PENDING !== $this->paymentApprovalStatus) {
            throw new \DomainException('Only invoices pending payment approval can be rejected.');
        }

        $this->paymentApprovalStatus = self::PAYMENT_APPROVAL_REJECTED;
        $this->paymentCurrentLevel = 0;

        return $this;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return Collection|MetaTableTypeInterface[]
     */
    public function getMetaFields(): Collection
    {
        return $this->meta;
    }

    /**
     * @return MetaTableTypeInterface[]
     */
    public function getVisibleMetaFields(): array
    {
        $all = [];
        foreach ($this->meta as $meta) {
            if ($meta->isVisible()) {
                $all[] = $meta;
            }
        }

        return $all;
    }

    public function getMetaField(string $name): ?MetaTableTypeInterface
    {
        foreach ($this->meta as $field) {
            if (strtolower($field->getName()) === strtolower($name)) {
                return $field;
            }
        }

        return null;
    }

    public function setMetaField(MetaTableTypeInterface $meta): EntityWithMetaFields
    {
        if (null === ($current = $this->getMetaField($meta->getName()))) {
            $meta->setEntity($this);
            $this->meta->add($meta);

            return $this;
        }

        $current->merge($meta);

        return $this;
    }

    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = \DateTime::createFromInterface($createdAt);
        $this->timezone = $createdAt->getTimezone()->getName();
    }

    public function setTotal(float $total): void
    {
        $this->total = $total;
    }

    public function setTax(float $tax): void
    {
        $this->tax = $tax;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setDueDays(int $dueDays): void
    {
        $this->dueDays = $dueDays;
    }

    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
        }

        $currentMeta = $this->meta;
        $this->meta = new ArrayCollection();
        /** @var InvoiceMeta $meta */
        foreach ($currentMeta as $meta) {
            $newMeta = clone $meta;
            $newMeta->setEntity($this);
            $this->setMetaField($newMeta);
        }
    }
}
