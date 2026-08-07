<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationEmail::class)]
class QuotationEmailTest extends TestCase
{
    public function testOnlyHashAndNotRawTokenIsStored(): void
    {
        $rawToken = 'opaque-random-token';
        $audit = new QuotationEmail(
            (new Quotation())->setCustomer(new Customer('Acme')),
            'client@example.com',
            hash('sha256', $rawToken),
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable()
        );

        self::assertSame(hash('sha256', $rawToken), $audit->getTokenHash());
        self::assertNotSame($rawToken, $audit->getTokenHash());
    }

    public function testResponseIsSingleUseAndAudited(): void
    {
        $audit = new QuotationEmail(
            (new Quotation())->setCustomer(new Customer('Acme')),
            'client@example.com',
            str_repeat('a', 64),
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable()
        );
        $at = new \DateTimeImmutable();
        $audit->recordResponse('accepted', $at, '192.0.2.1', 'test-agent');

        self::assertSame('accepted', $audit->getResponse());
        self::assertSame($at, $audit->getRespondedAt());
        self::assertSame('192.0.2.1', $audit->getResponseIp());
        $this->expectException(\DomainException::class);
        $audit->recordResponse('rejected', $at, null, null);
    }
}
