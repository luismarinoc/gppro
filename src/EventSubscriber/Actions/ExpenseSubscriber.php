<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber\Actions;

use App\Entity\Expense;
use App\Event\PageActionsEvent;

final class ExpenseSubscriber extends AbstractActionsSubscriber
{
    public static function getActionName(): string
    {
        return 'expense';
    }

    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();

        /** @var Expense $expense */
        $expense = $payload['expense'];

        if ($expense->getId() === null) {
            return;
        }

        if ($this->isGranted('edit_expense', $expense)) {
            $event->addEdit($this->path('expense_edit', ['id' => $expense->getId()]), false);
        }

        if ($this->isGranted('view_expense', $expense)) {
            $event->addAction('view', ['url' => $this->path('expense_view', ['id' => $expense->getId()])]);
            $event->addAction('download', ['url' => $this->path('expense_pdf', ['id' => $expense->getId()]), 'target' => '_blank']);
        }

        if ($this->isGranted('delete_expense', $expense)) {
            $event->addDivider();
            $event->addDelete($this->path('expense_delete', ['id' => $expense->getId(), 'token' => $payload['token']]), false);
        }
    }
}
