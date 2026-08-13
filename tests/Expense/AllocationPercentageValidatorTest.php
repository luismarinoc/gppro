<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Expense\AllocationPercentageValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllocationPercentageValidator::class)]
class AllocationPercentageValidatorTest extends TestCase
{
    public function testDraftAllowsSumUnderOneHundred(): void
    {
        $sut = new AllocationPercentageValidator();

        self::assertTrue($sut->isValidForDraft(['40.00', '50.00']));
    }

    public function testDraftAllowsSumExactlyOneHundred(): void
    {
        $sut = new AllocationPercentageValidator();

        self::assertTrue($sut->isValidForDraft(['60.00', '40.00']));
    }

    public function testDraftRejectsSumOverOneHundred(): void
    {
        $sut = new AllocationPercentageValidator();

        // spec: "GIVEN a draft with allocations totaling 90%, WHEN the user
        // adds one that would total 110%, THEN the change is rejected"
        self::assertFalse($sut->isValidForDraft(['90.00', '20.00']));
    }

    public function testSubmitRejectsSumUnderOneHundred(): void
    {
        $sut = new AllocationPercentageValidator();

        // spec: "GIVEN a draft with allocations totaling 90%, WHEN the user
        // submits for approval, THEN submission is rejected"
        self::assertFalse($sut->isValidForSubmit(['90.00']));
    }

    public function testSubmitAcceptsSumExactlyOneHundred(): void
    {
        $sut = new AllocationPercentageValidator();

        self::assertTrue($sut->isValidForSubmit(['33.33', '33.33', '33.34']));
    }
}
