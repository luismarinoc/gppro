<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Model;

class TimesheetCountedStatistic implements \JsonSerializable
{
    private int $counter = 0;
    private int $recordDuration = 0;
    private float $recordRate = 0.0;
    private float $internalRate = 0.0;

    private int $counterBillable = 0;
    private int $recordDurationBillable = 0;
    private float $recordRateBillable = 0.0;
    private float $internalRateBillable = 0.0;

    private float $recordRateBillableExported = 0.0;
    private int $recordDurationBillableExported = 0;

    // Unlike *BillableExported (driven by the `exported` flag, shared with
    // the unrelated CSV/Excel export feature), these are driven by an actual
    // invoice link — see Timesheet::$invoice / TimesheetInvoiceLinkSubscriber.
    private float $recordRateBillableInvoiced = 0.0;
    private int $recordDurationBillableInvoiced = 0;

    private int $counterExported = 0;
    private int $recordDurationExported = 0;
    private float $recordRateExported = 0.0;
    private float $internalRateExported = 0.0;

    /**
     * For unified access, used in frontend.
     */
    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): void
    {
        $this->counter = $counter;
    }

    public function addCounter(int $counter): void
    {
        $this->counter += $counter;
    }

    public function getCounterBillable(): int
    {
        return $this->counterBillable;
    }

    public function setCounterBillable(int $counter): void
    {
        $this->counterBillable = $counter;
    }

    public function addCounterBillable(int $counter): void
    {
        $this->counterBillable += $counter;
    }

    public function getCounterExported(): int
    {
        return $this->counterExported;
    }

    public function setCounterExported(int $counter): void
    {
        $this->counterExported = $counter;
    }

    public function addCounterExported(int $counter): void
    {
        $this->counterExported += $counter;
    }

    /**
     * For unified access, used in frontend.
     */
    public function getValue(): int
    {
        return $this->getDuration();
    }

    public function setDuration(int $duration): void
    {
        $this->recordDuration = $duration;
    }

    public function addDuration(int $duration): void
    {
        $this->recordDuration += $duration;
    }

    /**
     * For unified access, used in frontend.
     */
    public function getDuration(): int
    {
        return $this->recordDuration;
    }

    /**
     * For unified access, used in frontend.
     */
    public function getRate(): float
    {
        return $this->recordRate;
    }

    public function setRate(float $rate): void
    {
        $this->recordRate = $rate;
    }

    public function addRate(float $rate): void
    {
        $this->recordRate += $rate;
    }

    /**
     * Returns the total internal rate of all included timesheet records.
     */
    public function getInternalRate(): float
    {
        return $this->internalRate;
    }

    public function getInternalRateBillable(): float
    {
        return $this->internalRateBillable;
    }

    public function setInternalRateBillable(float $internalRateBillable): void
    {
        $this->internalRateBillable = $internalRateBillable;
    }

    public function addInternalRateBillable(float $internalRateBillable): void
    {
        $this->internalRateBillable += $internalRateBillable;
    }

    public function getInternalRateExported(): float
    {
        return $this->internalRateExported;
    }

    public function setInternalRateExported(float $internalRateExported): void
    {
        $this->internalRateExported = $internalRateExported;
    }

    public function addInternalRateExported(float $internalRateExported): void
    {
        $this->internalRateExported += $internalRateExported;
    }

    public function setInternalRate(float $internalRate): void
    {
        $this->internalRate = $internalRate;
    }

    public function addInternalRate(float $internalRate): void
    {
        $this->internalRate += $internalRate;
    }

    public function getDurationBillable(): int
    {
        return $this->recordDurationBillable;
    }

    public function setDurationBillable(int $recordDuration): void
    {
        $this->recordDurationBillable = $recordDuration;
    }

    public function addDurationBillable(int $recordDuration): void
    {
        $this->recordDurationBillable += $recordDuration;
    }

    public function getDurationBillableExported(): int
    {
        return $this->recordDurationBillableExported;
    }

    public function setDurationBillableExported(int $recordDuration): void
    {
        $this->recordDurationBillableExported = $recordDuration;
    }

    public function addDurationBillableExported(int $recordDuration): void
    {
        $this->recordDurationBillableExported += $recordDuration;
    }

    public function getRateBillable(): float
    {
        return $this->recordRateBillable;
    }

    public function setRateBillable(float $recordRate): void
    {
        $this->recordRateBillable = $recordRate;
    }

    public function addRateBillable(float $recordRate): void
    {
        $this->recordRateBillable += $recordRate;
    }

    public function getRateBillableExported(): float
    {
        return $this->recordRateBillableExported;
    }

    public function setRateBillableExported(float $recordRate): void
    {
        $this->recordRateBillableExported = $recordRate;
    }

    public function addRateBillableExported(float $recordRate): void
    {
        $this->recordRateBillableExported += $recordRate;
    }

    public function getRateBillableInvoiced(): float
    {
        return $this->recordRateBillableInvoiced;
    }

    public function setRateBillableInvoiced(float $recordRate): void
    {
        $this->recordRateBillableInvoiced = $recordRate;
    }

    public function addRateBillableInvoiced(float $recordRate): void
    {
        $this->recordRateBillableInvoiced += $recordRate;
    }

    public function getDurationBillableInvoiced(): int
    {
        return $this->recordDurationBillableInvoiced;
    }

    public function setDurationBillableInvoiced(int $recordDuration): void
    {
        $this->recordDurationBillableInvoiced = $recordDuration;
    }

    public function addDurationBillableInvoiced(int $recordDuration): void
    {
        $this->recordDurationBillableInvoiced += $recordDuration;
    }

    public function getDurationExported(): int
    {
        return $this->recordDurationExported;
    }

    public function setDurationExported(int $recordDuration): void
    {
        $this->recordDurationExported = $recordDuration;
    }

    public function addDurationExported(int $recordDuration): void
    {
        $this->recordDurationExported += $recordDuration;
    }

    public function getRateExported(): float
    {
        return $this->recordRateExported;
    }

    public function setRateExported(float $recordRate): void
    {
        $this->recordRateExported = $recordRate;
    }

    public function addRateExported(float $recordRate): void
    {
        $this->recordRateExported += $recordRate;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'duration' => $this->recordDuration,
            'duration_billable' => $this->recordDurationBillable,
            'duration_exported' => $this->recordDurationExported,
            'duration_billable_exported' => $this->recordDurationBillableExported,
            'duration_billable_invoiced' => $this->recordDurationBillableInvoiced,
            'rate' => $this->recordRate,
            'rate_billable' => $this->recordRateBillable,
            'rate_exported' => $this->recordRateExported,
            'rate_billable_exported' => $this->recordRateBillableExported,
            'rate_billable_invoiced' => $this->recordRateBillableInvoiced,
            'rate_internal' => $this->internalRate,
            'rate_internal_exported' => $this->internalRateExported,
            'amount' => $this->counter,
            'amount_billable' => $this->counterBillable,
            'amount_exported' => $this->counterExported,
        ];
    }
}
