<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Activity;
use App\Entity\ExportableItem;
use App\Entity\MetaTableTypeInterface;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\User;
use App\FxRate\ClpConversion;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Presents a Milestone + its already-computed CLP conversion as an
 * ExportableItem, so it can flow through the untouched invoice rendering
 * pipeline (calculators, hydrators, InvoiceService::createInvoice()).
 *
 * begin/end are NEVER the entity's own $dueDate instance: every call to
 * getBegin()/getEnd() returns a fresh \DateTime clone, normalized to
 * midnight, of `dueDate ?? createdAt`. This keeps the invariant that no
 * calculator or InvoiceQuery::setBegin()/setEnd() can ever mutate Milestone
 * state (see design D1).
 */
final class MilestoneInvoiceItem implements ExportableItem
{
    private readonly \DateTime $date;

    public function __construct(
        private readonly Milestone $milestone,
        private readonly ClpConversion $conversion,
    ) {
        $dueDate = $this->milestone->getDueDate();

        if (null !== $dueDate) {
            $date = clone $dueDate;
        } else {
            $createdAt = $this->milestone->getCreatedAt();
            $date = null !== $createdAt ? \DateTime::createFromInterface($createdAt) : new \DateTime();
        }

        $date->setTime(0, 0, 0);
        $this->date = $date;
    }

    public function getMilestone(): Milestone
    {
        return $this->milestone;
    }

    public function getId(): ?int
    {
        return $this->milestone->getId();
    }

    public function isExported(): bool
    {
        return $this->milestone->isInvoiced();
    }

    public function isBillable(): bool
    {
        return true;
    }

    public function getMetaField(string $name): ?MetaTableTypeInterface
    {
        return null;
    }

    /**
     * @return string[]
     */
    public function getTagsAsArray(): array
    {
        return [];
    }

    public function getAmount(): float
    {
        return 1.0;
    }

    public function getActivity(): ?Activity
    {
        return null;
    }

    public function getProject(): ?Project
    {
        return $this->milestone->getProject();
    }

    public function getFixedRate(): float
    {
        return (float) $this->conversion->clpAmount;
    }

    public function getHourlyRate(): ?float
    {
        return null;
    }

    public function getRate(): float
    {
        return (float) $this->conversion->clpAmount;
    }

    public function getInternalRate(): ?float
    {
        return null;
    }

    public function getUser(): ?User
    {
        return null;
    }

    public function getBegin(): \DateTime
    {
        return clone $this->date;
    }

    public function getEnd(): \DateTime
    {
        return clone $this->date;
    }

    public function getDuration(): ?int
    {
        return null;
    }

    public function getDescription(): string
    {
        $name = (string) $this->milestone->getName();

        if (!$this->conversion->isConverted()) {
            return \sprintf('%s — %s CLP', $name, $this->conversion->clpAmount);
        }

        return \sprintf(
            '%s — %s %s × %s (%s) = %s CLP',
            $name,
            $this->conversion->sourceAmount,
            $this->conversion->sourceCurrency,
            $this->conversion->rate,
            $this->conversion->rateDate?->format('Y-m-d'),
            $this->conversion->clpAmount
        );
    }

    /**
     * @return MetaTableTypeInterface[]
     */
    public function getVisibleMetaFields(): array
    {
        return [];
    }

    /**
     * @return Collection<int, MetaTableTypeInterface>
     */
    public function getMetaFields(): Collection
    {
        return new ArrayCollection();
    }

    public function getType(): string
    {
        return 'milestone';
    }

    public function getCategory(): string
    {
        return 'milestone';
    }
}
