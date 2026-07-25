<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\FxRate;

use App\Entity\FxRate;
use App\FxRate\MindicadorClient;
use App\FxRate\MindicadorUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MindicadorClient::class)]
class MindicadorClientTest extends TestCase
{
    public function testRequestsCorrectUrlForIndicatorAndDate(): void
    {
        $requestedUrl = null;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['serie' => [['valor' => 39012.34]]], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $sut = new MindicadorClient($client);
        $sut->fetchValue(FxRate::INDICATOR_UF, new \DateTimeImmutable('2026-07-20'));

        self::assertSame('https://mindicador.cl/api/uf/20-07-2026', $requestedUrl);
    }

    public function testValueIsReturnedForSuccessfulResponseWithData(): void
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['serie' => [['valor' => 950.5]]], \JSON_THROW_ON_ERROR), ['http_code' => 200])
        );

        $sut = new MindicadorClient($client);
        $value = $sut->fetchValue(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-07-20'));

        self::assertSame('950.5', $value);
    }

    public function testNullIsReturnedForEmptySerie(): void
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['serie' => []], \JSON_THROW_ON_ERROR), ['http_code' => 200])
        );

        $sut = new MindicadorClient($client);
        $value = $sut->fetchValue(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-07-19'));

        self::assertNull($value);
    }

    public function testNullIsReturnedForNullValor(): void
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['serie' => [['valor' => null]]], \JSON_THROW_ON_ERROR), ['http_code' => 200])
        );

        $sut = new MindicadorClient($client);
        $value = $sut->fetchValue(FxRate::INDICATOR_UF, new \DateTimeImmutable('2026-07-19'));

        self::assertNull($value);
    }

    public function testNullIsReturnedFor404(): void
    {
        $client = new MockHttpClient(
            new MockResponse('not found', ['http_code' => 404])
        );

        $sut = new MindicadorClient($client);
        $value = $sut->fetchValue(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-07-20'));

        self::assertNull($value);
    }

    public function testExceptionIsThrownFor500(): void
    {
        $client = new MockHttpClient(
            new MockResponse('server error', ['http_code' => 500])
        );

        $sut = new MindicadorClient($client);

        $this->expectException(MindicadorUnavailableException::class);
        $sut->fetchValue(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-07-20'));
    }

    public function testExceptionIsThrownForMalformedJson(): void
    {
        $client = new MockHttpClient(
            new MockResponse('{not-valid-json', ['http_code' => 200])
        );

        $sut = new MindicadorClient($client);

        $this->expectException(MindicadorUnavailableException::class);
        $sut->fetchValue(FxRate::INDICATOR_UF, new \DateTimeImmutable('2026-07-20'));
    }

    public function testExceptionIsThrownForTransportFailure(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('Connection timed out');
        });

        $sut = new MindicadorClient($client);

        $this->expectException(MindicadorUnavailableException::class);
        $sut->fetchValue(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-07-20'));
    }
}
