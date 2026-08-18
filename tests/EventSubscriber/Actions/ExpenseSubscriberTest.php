<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber\Actions;

use App\EventSubscriber\Actions\ExpenseSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExpenseSubscriber::class)]
class ExpenseSubscriberTest extends AbstractActionsSubscriberTestCase
{
    public function testEventName(): void
    {
        $this->assertGetSubscribedEvent(ExpenseSubscriber::class, 'expense');
    }
}
