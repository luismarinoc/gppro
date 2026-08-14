<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Activity;
use App\Entity\ActivityComment;
use App\Entity\CommentTableTypeTrait;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CommentTableTypeTrait::class)]
#[CoversClass(ActivityComment::class)]
class ActivityCommentTest extends AbstractCommentEntityTestCase
{
    protected function getEntity(): ActivityComment
    {
        return new ActivityComment(new Activity());
    }

    public function testConstructorHoldsGivenActivity(): void
    {
        $activity = new Activity();

        $comment = new ActivityComment($activity);

        self::assertSame($activity, $comment->getActivity());
    }
}
