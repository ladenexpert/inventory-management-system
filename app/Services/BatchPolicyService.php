<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Setting;
use Carbon\CarbonInterface;

class BatchPolicyService
{
    public const DEFAULT_NEAR_EXPIRY_DAYS = 30;
    public const NEAR_EXPIRY_SETTING_KEY = 'batch_near_expiry_days';

    public function getStatus(Batch $batch, ?CarbonInterface $asOf = null): BatchStatus
    {
        if ($this->isQuarantined($batch)) {
            return BatchStatus::QUARANTINED;
        }

        if ($this->isDepleted($batch)) {
            return BatchStatus::DEPLETED;
        }

        if ($this->isExpired($batch, $asOf)) {
            return BatchStatus::EXPIRED;
        }

        if ($this->isNearExpiry($batch, $asOf)) {
            return BatchStatus::NEAR_EXPIRY;
        }

        return BatchStatus::ACTIVE;
    }

    public function isExpired(Batch $batch, ?CarbonInterface $asOf = null): bool
    {
        if (!$batch->expiry_date) {
            return false;
        }

        return $batch->expiry_date->lt($this->today($asOf));
    }

    public function isNearExpiry(Batch $batch, ?CarbonInterface $asOf = null, ?int $thresholdDays = null): bool
    {
        if (!$batch->expiry_date || $this->isExpired($batch, $asOf) || $this->isDepleted($batch) || $this->isQuarantined($batch)) {
            return false;
        }

        $today = $this->today($asOf);
        $threshold = $thresholdDays ?? $this->nearExpiryThresholdDays();

        return $batch->expiry_date->between(
            $today,
            $today->copy()->addDays($threshold)->endOfDay(),
        );
    }

    public function canBeConsumed(Batch $batch, ?CarbonInterface $asOf = null): bool
    {
        return !$this->isQuarantined($batch)
            && !$this->isDepleted($batch)
            && !$this->isExpired($batch, $asOf);
    }

    public function canBeSold(Batch $batch, ?CarbonInterface $asOf = null): bool
    {
        return $this->canBeConsumed($batch, $asOf);
    }

    public function inventoryValue(Batch $batch): int
    {
        return (int) $batch->available_quantity * (int) $batch->unit_cost;
    }

    public function nearExpiryThresholdDays(): int
    {
        $configured = Setting::get(self::NEAR_EXPIRY_SETTING_KEY);

        if ($configured === null || !is_numeric($configured)) {
            return self::DEFAULT_NEAR_EXPIRY_DAYS;
        }

        return max(0, (int) $configured);
    }

    public function isDepleted(Batch $batch): bool
    {
        return (int) $batch->available_quantity <= 0;
    }

    public function isQuarantined(Batch $batch): bool
    {
        return $batch->source === BatchStatus::QUARANTINED->value;
    }

    protected function today(?CarbonInterface $asOf = null): CarbonInterface
    {
        return ($asOf ? $asOf->copy() : now())->startOfDay();
    }
}
