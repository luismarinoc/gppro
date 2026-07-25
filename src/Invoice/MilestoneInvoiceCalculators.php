<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

/**
 * Whitelist of InvoiceTemplate calculator ids that are safe to use for a
 * milestone invoice.
 *
 * Confirmed by the PR3 R1 regression sweep
 * (tests/Invoice/Calculator/MilestoneCalculatorCompatibilityTest.php): 4 of
 * the 12 shipped calculators (user, activity_user, project_user, date_user)
 * unconditionally throw for any MilestoneInvoiceItem because
 * MilestoneInvoiceItem::getUser() is always null and those 4 calculators
 * require a persisted user. The milestone invoicing flow must never offer
 * one of those 4 as a selectable template/calculator, so the list below is
 * the single source of truth used to filter the template selector
 * (InvoiceTemplateRepository::getQueryBuilderForMilestoneFormType()).
 */
final class MilestoneInvoiceCalculators
{
    /**
     * @var string[]
     */
    public const COMPATIBLE = [
        'default',
        'short',
        'price',
        'date',
        'weekly',
        'activity',
        'project',
        'project_activity',
    ];

    private function __construct()
    {
        // static-only class, never instantiated
    }
}
