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
use App\Entity\Project;
use App\Entity\QuotationLine;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class QuotationInvoiceItem implements ExportableItem
{
    private readonly \DateTime $date;

    public function __construct(private readonly QuotationLine $line)
    {
        $this->date = new \DateTime('now');
        $this->date->setTime(0, 0, 0);
    }

    public function getLine(): QuotationLine { return $this->line; }

    public function getId(): ?int { return $this->line->getId(); }

    public function isExported(): bool { return false; }

    public function isBillable(): bool { return true; }

    public function getMetaField(string $name): ?MetaTableTypeInterface { return null; }

    public function getTagsAsArray(): array { return []; }

    public function getAmount(): float { return (float) ($this->line->getQuantity() ?? 0); }

    public function getActivity(): ?Activity { return null; }

    public function getProject(): ?Project { return $this->line->getQuotation()?->getProject(); }

    public function getFixedRate(): ?float { return $this->line->getUnitPrice() === null ? null : (float) $this->line->getUnitPrice(); }

    public function getHourlyRate(): ?float { return null; }

    public function getRate(): float { return $this->getAmount() * ($this->getFixedRate() ?? 0); }

    public function getInternalRate(): ?float { return null; }

    public function getUser(): ?User { return null; }

    public function getBegin(): \DateTime { return clone $this->date; }

    public function getEnd(): \DateTime { return clone $this->date; }

    public function getDuration(): ?int { return null; }

    public function getDescription(): string
    {
        return $this->line->getDescription() ?? '';
    }

    public function getVisibleMetaFields(): array { return []; }

    /** @return Collection<int, MetaTableTypeInterface> */
    public function getMetaFields(): Collection { return new ArrayCollection(); }

    public function getType(): string { return 'quotation'; }

    public function getCategory(): string { return 'quotation'; }
}
