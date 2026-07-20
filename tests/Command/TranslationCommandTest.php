<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\TranslationCommand;
use App\Configuration\LocaleService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(TranslationCommand::class)]
#[Group('integration')]
class TranslationCommandTest extends KernelTestCase
{
    public function testCommandName(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->add(new TranslationCommand(
            __DIR__ . '/../../',
            'test',
            new LocaleService([])
        ));

        self::assertTrue($application->has('gppro:translations'));
        $command = $application->find('gppro:translations');
        self::assertInstanceOf(TranslationCommand::class, $command);
    }

    public function testCommandNameIsNotEnabledInProd(): void
    {
        $sut = new TranslationCommand(
            __DIR__ . '/../../',
            'prod',
            new LocaleService([])
        );
        self::assertFalse($sut->isEnabled());
    }
}
