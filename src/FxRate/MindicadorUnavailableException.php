<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

/**
 * A real failure fetching or parsing a mindicador.cl response: transport
 * error, non-404 non-2xx status, or malformed JSON.
 *
 * NOT thrown for a legitimate "no data for this date" answer (404, empty
 * `serie`, or a null `valor`) — that case returns null from
 * MindicadorClient::fetchValue() instead, see that method's docblock.
 */
final class MindicadorUnavailableException extends \RuntimeException
{
}
