<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Expense;

interface ExpensePdfRendererInterface
{
    public function render(Expense $expense): string;
}
