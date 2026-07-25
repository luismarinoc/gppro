<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

class FxRateQuery extends BaseQuery implements DateRangeInterface
{
    use DateRangeTrait;

    private ?string $indicator = null;

    public function __construct()
    {
        $this->setDefaults([
            'orderBy' => 'date',
            'order' => self::ORDER_DESC,
        ]);
    }

    public function getIndicator(): ?string
    {
        return $this->indicator;
    }

    public function setIndicator(?string $indicator): self
    {
        $this->indicator = $indicator;

        return $this;
    }
}
