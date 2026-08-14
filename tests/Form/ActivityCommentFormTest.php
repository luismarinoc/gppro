<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Entity\Activity;
use App\Entity\ActivityComment;
use App\Form\ActivityCommentForm;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ActivityCommentForm::class)]
class ActivityCommentFormTest extends TypeTestCase
{
    public function testMessageFieldIsPresentAndUnlabelled(): void
    {
        $form = $this->factory->createBuilder(ActivityCommentForm::class, new ActivityComment(new Activity()));

        self::assertTrue($form->has('message'));
        self::assertFalse($form->get('message')->getOption('label'));
    }

    public function testSubmitValidDataSetsMessageOnComment(): void
    {
        $comment = new ActivityComment(new Activity());
        $form = $this->factory->create(ActivityCommentForm::class, $comment);

        $form->submit(['message' => 'Looks good to me']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('Looks good to me', $comment->getMessage());
    }

    public function testCsrfTokenIdIsDedicatedToActivityComments(): void
    {
        $form = $this->factory->createBuilder(ActivityCommentForm::class, new ActivityComment(new Activity()));

        self::assertEquals('admin_activity_comment', $form->getFormConfig()->getOption('csrf_token_id'));
    }
}
