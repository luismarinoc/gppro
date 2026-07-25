<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

use App\Entity\Milestone;

/**
 * Carries the milestones selected for a milestone invoice. Marker type used
 * by MilestoneInvoiceItemRepository to gate itself out of the ordinary
 * timesheet fan-out (InvoiceService::getInvoiceItems() calls every tagged
 * InvoiceItemRepositoryInterface with a plain InvoiceQuery).
 *
 * Deliberately named `milestones` (plural): the inherited
 * InvoiceQuery::$milestone (singular) is a pre-existing, unrelated filter
 * and keeps its own meaning untouched.
 */
final class MilestoneInvoiceQuery extends InvoiceQuery
{
    /**
     * @var Milestone[]
     */
    private array $milestones = [];

    /**
     * @param Milestone[] $milestones
     */
    public function setMilestones(array $milestones): self
    {
        $this->milestones = $milestones;

        return $this;
    }

    /**
     * @return Milestone[]
     */
    public function getMilestones(): array
    {
        return $this->milestones;
    }
}
