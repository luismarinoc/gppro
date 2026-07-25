<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber\Actions;

use App\EventSubscriber\Actions\FxRatesSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FxRatesSubscriber::class)]
class FxRatesSubscriberTest extends AbstractActionsSubscriberTestCase
{
    public function testEventName(): void
    {
        $this->assertGetSubscribedEvent(FxRatesSubscriber::class, 'fx_rates');
    }
}
