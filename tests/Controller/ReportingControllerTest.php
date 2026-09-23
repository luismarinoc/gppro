<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class ReportingControllerTest extends AbstractControllerBaseTestCase
{
    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/reporting/');
    }

    public function testOverviewPage(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/reporting/');
        $nodes = $client->getCrawler()->filter('section.content div.row-cards a.card-link');
        self::assertCount(11, $nodes);

        $crawler = $client->getCrawler();
        self::assertCount(1, $crawler->filter('section.content div.row-cards.gp-reporting > ul.gp-reporting-list[role="list"]'));
        $items = $crawler->filter('section.content div.row-cards.gp-reporting > ul.gp-reporting-list > li.gp-reporting-item');
        self::assertCount(11, $items);
        foreach ($items as $item) {
            $links = (new \Symfony\Component\DomCrawler\Crawler($item))->filter('a.card-link.gp-reporting-link');
            self::assertCount(1, $links);
            self::assertCount(1, $links->filter('.gp-reporting-icon[aria-hidden="true"] > i.icon'));
            self::assertCount(1, $links->filter('.gp-reporting-label'));
            self::assertNotSame('', trim($links->filter('.gp-reporting-label')->text()));
        }
    }

    public function testAllReports(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->request($client, '/reporting/');
        $nodes = $client->getCrawler()->filter('section.content div.row-cards a.card-link');
        self::assertCount(11, $nodes);
        foreach ($nodes as $node) {
            self::assertNotNull($node->attributes);
            $link = $node->attributes->getNamedItem('href');
            self::assertNotNull($link);
            $url = $link->nodeValue;
            self::assertNotNull($url);
            self::assertNotEmpty($url);
            self::assertStringStartsWith('/en/reporting/', $url);
            $this->request($client, $url);
        }
    }

    public function testOverviewPageAsUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->request($client, '/reporting/');
        $nodes = $client->getCrawler()->filter('section.content div.row-cards a.card-link');
        self::assertCount(3, $nodes);
    }
}
