<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

use App\Entity\User;

/**
 * Filters for the ROLE_SUPER_ADMIN-only login audit list: by user, date
 * range, and outcome (per login-audit-trail spec's filterable requirement).
 */
class LoginAttemptQuery extends BaseQuery
{
    private ?User $user = null;
    private ?string $outcome = null;
    private ?\DateTimeImmutable $dateFrom = null;
    private ?\DateTimeImmutable $dateTo = null;

    public function __construct()
    {
        $this->setDefaults([
            'orderBy' => 'createdAt',
            'order' => self::ORDER_DESC,
        ]);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function setOutcome(?string $outcome): self
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getDateFrom(): ?\DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function setDateFrom(?\DateTimeImmutable $dateFrom): self
    {
        $this->dateFrom = $dateFrom;

        return $this;
    }

    public function getDateTo(): ?\DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function setDateTo(?\DateTimeImmutable $dateTo): self
    {
        $this->dateTo = $dateTo;

        return $this;
    }
}
