<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Entity\Quotation;
use App\Event\PageActionsEvent;

final class QuotationSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'quotation';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        /** @var Quotation $quotation */
        $quotation = $payload['quotation'];

        if ($quotation->getId() === null) {
            return;
        }

        if ($this->isGranted('edit_quotation', $quotation)) {
            $event->addEdit($this->path('quotation_edit', ['id' => $quotation->getId()]), false);
        }

        if ($this->isGranted('view_quotation', $quotation)) {
            $event->addAction('view', ['url' => $this->path('quotation_view', ['id' => $quotation->getId()])]);
            $event->addAction('download', ['url' => $this->path('quotation_pdf', ['id' => $quotation->getId()]), 'target' => '_blank']);
        }
    }
}
