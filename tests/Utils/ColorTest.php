<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Utils;

use App\Constants;
use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Utils\Color;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Color::class)]
class ColorTest extends TestCase
{
    public function testGetColorAndGetTimesheetColor(): void
    {
        $sut = new Color();

        $globalActivity = new Activity();
        self::assertNull($sut->getColor($globalActivity));

        $globalActivity->setColor('#000001');
        self::assertEquals('#000001', $sut->getColor($globalActivity));

        $customer = new Customer('foo');
        self::assertNull($sut->getColor($customer));

        $customer->setColor('#000004');
        self::assertEquals('#000004', $sut->getColor($customer));

        $project = new Project();
        self::assertNull($sut->getColor($project));

        $project->setCustomer($customer);
        self::assertEquals('#000004', $sut->getColor($project));

        $project->setColor('#000003');
        self::assertEquals('#000003', $sut->getColor($project));

        $activity = new Activity();
        self::assertNull($sut->getColor($activity));

        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setProject($project);
        self::assertEquals('#000003', $sut->getColor($timesheet));
        self::assertEquals('#000003', $sut->getTimesheetColor($timesheet));

        $activity->setProject($project);
        self::assertEquals('#000003', $sut->getColor($activity));

        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setProject($project);
        self::assertEquals('#000003', $sut->getColor($timesheet));
        self::assertEquals('#000003', $sut->getTimesheetColor($timesheet));

        $activity->setColor('#000002');
        self::assertEquals('#000002', $sut->getColor($activity));

        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setProject($project);
        self::assertEquals('#000002', $sut->getColor($timesheet));
        self::assertEquals('#000002', $sut->getTimesheetColor($timesheet));

        $timesheet = new Timesheet();
        self::assertEquals(Constants::DEFAULT_COLOR, $sut->getTimesheetColor($timesheet));
        self::assertNull($sut->getColor($timesheet));
        self::assertEquals(Constants::DEFAULT_COLOR, $sut->getColor($timesheet, true));

        $timesheet = new Timesheet();
        $timesheet->setActivity(new Activity());
        $project = new Project();
        $customer = new Customer('foo');
        $customer->setColor('#123456');
        $project->setCustomer($customer);
        $timesheet->setProject($project);
        self::assertEquals('#123456', $sut->getColor($timesheet, true));
    }

    public function testGetFontContrastColor(): void
    {
        $sut = new Color();
        self::assertEquals('#ffffff', $sut->getFontContrastColor('#666'));
        self::assertEquals('#ffffff', $sut->getFontContrastColor('#666666'));
        self::assertEquals('#ffffff', $sut->getFontContrastColor('#000000'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#ccc'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#cccccc'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#ffffff'));
    }

    public function testGetFontContrastColorPicksTheTextColorWithTheHigherContrast(): void
    {
        $sut = new Color();
        // mid-tone colors where the former YIQ rule chose white with a contrast below 4.5
        self::assertEquals('#000000', $sut->getFontContrastColor('#2196F3'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#00bb32'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#E91E63'));
        self::assertEquals('#ffffff', $sut->getFontContrastColor('#2f4f4f'));
    }

    public function testGetFontContrastColorMeetsAaContrastForEveryPaletteColor(): void
    {
        $sut = new Color();
        $reflection = new \ReflectionClass(Color::class);
        /** @var array<string> $palette */
        $palette = $reflection->getConstant('PALETTE');

        foreach ($palette as $color) {
            $text = $sut->getFontContrastColor($color);
            $background = $this->relativeLuminance($color);
            $foreground = $this->relativeLuminance($text);
            $ratio = (max($background, $foreground) + 0.05) / (min($background, $foreground) + 0.05);

            self::assertGreaterThanOrEqual(4.5, $ratio, \sprintf('Text %s on %s only reaches %.2f', $text, $color, $ratio));
        }
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(
            static function (string $value): float {
                $channel = hexdec($value) / 255;

                return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2)
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public function testGetFontContrastColorReturnsContrastForDefaultColorOnInvalidColor(): void
    {
        $sut = new Color();
        self::assertEquals('#000000', $sut->getFontContrastColor(''));
        self::assertEquals('#000000', $sut->getFontContrastColor('000000'));
        self::assertEquals('#000000', $sut->getFontContrastColor(Constants::DEFAULT_COLOR));
        self::assertEquals('#000000', $sut->getFontContrastColor('#6'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#66'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#6666'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#cccc'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#ccccc'));
        self::assertEquals('#000000', $sut->getFontContrastColor('#ccccccc'));
    }

    public function testGetRandomColor(): void
    {
        $sut = new Color();

        self::assertIsValidColor($sut->getRandomFromPalette('1234'));

        self::assertIsValidColor($sut->getRandom());
        self::assertIsValidColor($sut->getRandomColor());
        self::assertIsValidColor($sut->getRandom('1234'));
        self::assertEquals($sut->getRandom('1234'), $sut->getRandomFromPalette('1234'));
    }

    private static function assertIsValidColor(string $color)
    {
        self::assertStringStartsWith('#', $color);
        self::assertEquals(7, \strlen($color));
    }
}
