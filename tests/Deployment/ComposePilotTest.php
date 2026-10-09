<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Deployment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ComposePilotTest extends TestCase
{
    public function testIsolatedComposeContract(): void
    {
        $compose = Yaml::parseFile(__DIR__ . '/../../docker-compose.yml');

        self::assertSame(['127.0.0.1:${GPPRO_HOST_PORT:-8001}:8001'], $compose['services']['app']['ports']);
        self::assertSame([
            'gppro_data' => null,
            'gppro_plugins' => null,
            'gppro_db' => null,
        ], $compose['volumes']);
        self::assertSame([
            'gppro_data:/opt/gppro/var/data',
            'gppro_plugins:/opt/gppro/var/plugins',
        ], $compose['services']['app']['volumes']);
        self::assertSame(['gppro_db:/var/lib/mysql'], $compose['services']['mysql']['volumes']);
    }
}
