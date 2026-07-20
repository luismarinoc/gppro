<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\RegenerateLocalesCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(RegenerateLocalesCommand::class)]
#[Group('integration')]
class RegenerateLocalesCommandTest extends KernelTestCase
{
    public function testCommandName(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->add(new RegenerateLocalesCommand(__DIR__ . '/../../', 'test'));

        self::assertTrue($application->has('gppro:reset:locales'));
        $command = $application->find('gppro:reset:locales');
        self::assertInstanceOf(RegenerateLocalesCommand::class, $command);
    }

    public function testCommandNameIsNotEnabledInProd(): void
    {
        $sut = new RegenerateLocalesCommand(__DIR__ . '/../../', 'prod');
        self::assertFalse($sut->isEnabled());
    }
}
