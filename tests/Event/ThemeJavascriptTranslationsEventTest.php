<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Event;

use App\Event\ThemeJavascriptTranslationsEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThemeJavascriptTranslationsEvent::class)] // @phpstan-ignore classConstant.deprecatedClass
#[Group('legacy')]
class ThemeJavascriptTranslationsEventTest extends TestCase
{
    public const COUNTER = 19;

    public function testDefaultValues(): void
    {
        $sut = new ThemeJavascriptTranslationsEvent(); // @phpstan-ignore new.deprecatedClass

        self::assertCount(self::COUNTER, $sut->getTranslations()); // @phpstan-ignore method.deprecatedClass
    }

    public function testUserQuickActionConfirmationsAreRegistered(): void
    {
        $sut = new ThemeJavascriptTranslationsEvent(); // @phpstan-ignore new.deprecatedClass
        $result = $sut->getTranslations(); // @phpstan-ignore method.deprecatedClass

        self::assertArrayHasKey('confirm.force_password_reset', $result);
        self::assertEquals(['confirm.force_password_reset', 'messages'], $result['confirm.force_password_reset']);
        self::assertArrayHasKey('confirm.revoke_remember_me', $result);
        self::assertEquals(['confirm.revoke_remember_me', 'messages'], $result['confirm.revoke_remember_me']);
    }

    public function testGetterAndSetter(): void
    {
        $sut = new ThemeJavascriptTranslationsEvent(); // @phpstan-ignore new.deprecatedClass
        $sut->setTranslation('foo', 'bar'); // @phpstan-ignore method.deprecatedClass
        $sut->setTranslation('hello', 'world', 'testing'); // @phpstan-ignore method.deprecatedClass

        $result = $sut->getTranslations(); // @phpstan-ignore method.deprecatedClass
        self::assertCount(self::COUNTER + 2, $result);
        self::assertArrayHasKey('foo', $result);
        self::assertEquals(['bar', 'messages'], $result['foo']);
        self::assertArrayHasKey('hello', $result);
        self::assertEquals(['world', 'testing'], $result['hello']);
    }
}
