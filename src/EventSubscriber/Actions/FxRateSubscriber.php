<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Entity\FxRate;
use App\Event\PageActionsEvent;

final class FxRateSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'fx_rate';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        /** @var FxRate $fxRate */
        $fxRate = $payload['fx_rate'];
        $id = $fxRate->getId();

        if ($id === null) {
            return;
        }

        if ($this->isGranted('manage_fx_rate')) {
            $event->addEdit($this->path('fx_rates_edit', ['id' => $id]));
        }

        if ($event->isIndexView() && $this->isGranted('delete_fx_rate')) {
            $event->addDelete($this->path('fx_rates_delete', ['id' => $id]));
        }
    }
}
