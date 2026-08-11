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

#[ORM\Table(name: 'gppro_quotation_lines')]
#[ORM\Index(columns: ['quotation_id'])]
#[ORM\Entity]
class QuotationLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quotation::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Quotation $quotation = null;

    #[ORM\ManyToOne(targetEntity: QuotationCatalogItem::class)]
    #[ORM\JoinColumn(name: 'catalog_item_id', nullable: true, onDelete: 'SET NULL')]
    private ?QuotationCatalogItem $catalogItem = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 18, scale: 4, nullable: false)]
    #[Assert\Range(min: 0)]
    private ?string $unitPrice = null;

    #[ORM\Column(name: 'quantity', type: Types::DECIMAL, precision: 18, scale: 4, nullable: false)]
    #[Assert\Range(min: 0.0001)]
    private ?string $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuotation(): ?Quotation
    {
        return $this->quotation;
    }

    public function setQuotation(?Quotation $quotation): QuotationLine
    {
        $this->quotation = $quotation;

        return $this;
    }

    public function getCatalogItem(): ?QuotationCatalogItem
    {
        return $this->catalogItem;
    }

    public function setCatalogItem(?QuotationCatalogItem $catalogItem): QuotationLine
    {
        $this->catalogItem = $catalogItem;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): QuotationLine
    {
        $this->description = $description;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): QuotationLine
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): QuotationLine
    {
        $this->quantity = $quantity;

        return $this;
    }
}
