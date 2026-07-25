<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Event\PageActionsEvent;

final class FxRatesSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'fx_rates';
    }

    public function onActions(PageActionsEvent $event): void
    {
        if ($this->isGranted('create_fx_rate')) {
            $event->addCreate($this->path('fx_rates_create'));
        }
    }
}
