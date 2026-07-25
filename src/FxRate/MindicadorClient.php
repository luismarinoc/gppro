<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches daily USD ("dolar") and UF values from mindicador.cl.
 *
 * A 404, an empty `serie`, or a null `valor` all mean the same thing —
 * mindicador.cl has no value for the requested date (typically a weekend
 * or Chilean holiday) — and are treated identically as "no data", not as
 * an error. Only transport failures, non-404 non-2xx statuses, and
 * malformed JSON are real failures.
 *
 * Not declared `final`: FxRateSynchronizer's test double needs to mock this
 * class directly, mirroring how Repository classes are mocked throughout
 * this codebase (e.g. `$this->createMock(TimesheetRepository::class)`).
 */
class MindicadorClient
{
    private const BASE_URL = 'https://mindicador.cl/api';

    /**
     * Per-request timeout/max_duration in seconds. Retries are handled by the
     * RetryableHttpClient decorator wired in services.yaml, not here.
     */
    private const TIMEOUT = 10;
    private const MAX_DURATION = 15;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws MindicadorUnavailableException on transport error, a non-404
     *                                        non-2xx status, or malformed JSON
     *
     * @return string|null the published value as a string, or null when the
     *                     API answered but has no value for this date
     */
    public function fetchValue(string $indicator, \DateTimeImmutable $date): ?string
    {
        $url = \sprintf('%s/%s/%s', self::BASE_URL, $indicator, $date->format('d-m-Y'));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT,
                'max_duration' => self::MAX_DURATION,
            ]);

            if (404 === $response->getStatusCode()) {
                return null;
            }

            $data = $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new MindicadorUnavailableException(
                \sprintf('Failed fetching indicator "%s" for date "%s": %s', $indicator, $date->format('Y-m-d'), $e->getMessage()),
                0,
                $e
            );
        }

        $serie = \is_array($data['serie'] ?? null) ? $data['serie'] : [];

        if ([] === $serie) {
            return null;
        }

        $firstEntry = $serie[0] ?? null;
        $valor = \is_array($firstEntry) ? ($firstEntry['valor'] ?? null) : null;

        if (null === $valor || !\is_scalar($valor)) {
            return null;
        }

        return (string) $valor;
    }
}
