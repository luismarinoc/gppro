<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Doctrine\Behavior\ModifiedTrait;
use App\Repository\FxRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_fx_rates')]
#[ORM\UniqueConstraint(name: 'UNIQ_GPPRO_FX_RATES_DATE_INDICATOR', columns: ['date', 'indicator'])]
#[ORM\Entity(repositoryClass: FxRateRepository::class)]
class FxRate
{
    public const INDICATOR_USD = 'dolar';
    public const INDICATOR_UF = 'uf';

    /**
     * @var array<string>
     */
    public const INDICATORS = [self::INDICATOR_USD, self::INDICATOR_UF];

    use ModifiedTrait;

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    /**
     * The Chilean calendar date this rate applies to (never a UTC instant).
     */
    #[ORM\Column(name: 'date', type: Types::DATE_IMMUTABLE, nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(name: 'indicator', type: Types::STRING, length: 20, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::INDICATORS)]
    private ?string $indicator = null;

    /**
     * Stored as string to reproduce the value published by mindicador.cl byte-for-byte.
     */
    #[ORM\Column(name: 'rate_value', type: Types::DECIMAL, precision: 15, scale: 6, nullable: false)]
    #[Assert\NotBlank]
    private ?string $rateValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): FxRate
    {
        $this->date = $date;

        return $this;
    }

    public function getIndicator(): ?string
    {
        return $this->indicator;
    }

    public function setIndicator(string $indicator): FxRate
    {
        $this->indicator = $indicator;

        return $this;
    }

    public function getRateValue(): ?string
    {
        return $this->rateValue;
    }

    public function setRateValue(string $rateValue): FxRate
    {
        $this->rateValue = $rateValue;

        return $this;
    }
}
