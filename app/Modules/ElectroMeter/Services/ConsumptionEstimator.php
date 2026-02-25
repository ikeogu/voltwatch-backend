<?php

namespace App\Modules\ElectroMeter\Services;

use App\Models\Meter;
use App\Models\Appliance;
use App\Models\DailyUsageSummary;
use Carbon\Carbon;

/**
 * ConsumptionEstimator
 *
 * Core intelligence engine for VoltWatch MVP (Phase 1 — no IoT).
 * Estimates kWh consumption from the user's registered appliances,
 * calculates costs using the meter's tariff band, and predicts depletion.
 */
class ConsumptionEstimator
{
    public function __construct(protected Meter $meter) {}

    /**
     * Total estimated daily consumption in kWh across all appliances.
     */
    public function dailyKwh(): float
    {
        return $this->meter->appliances->sum(fn(Appliance $a) => $a->computeDailyKwh());
    }

    /**
     * Daily cost in NGN based on tariff band.
     */
    public function dailyCostNgn(): float
    {
        return round($this->dailyKwh() * ($this->meter->tariffBand?->rate_per_kwh ?? 0), 2);
    }

    /**
     * Monthly estimates (30 days).
     */
    public function monthlyKwh(): float
    {
        return round($this->dailyKwh() * 30, 3);
    }

    public function monthlyCostNgn(): float
    {
        return round($this->dailyCostNgn() * 30, 2);
    }

    /**
     * Estimated days remaining before units run out.
     */
    public function daysRemaining(): ?float
    {
        $daily = $this->dailyKwh();
        if ($daily <= 0) return null;
        return round($this->meter->current_units / $daily, 1);
    }

    /**
     * Estimated datetime of full depletion.
     */
    public function depletionAt(): ?Carbon
    {
        $days = $this->daysRemaining();
        return $days !== null ? now()->addHours($days * 24) : null;
    }

    /**
     * Breakdown of consumption per appliance with cost and percentage.
     */
    public function applianceBreakdown(): array
    {
        $totalKwh = $this->dailyKwh();
        $rate     = $this->meter->tariffBand?->rate_per_kwh ?? 0;

        return $this->meter->appliances->map(function (Appliance $appliance) use ($totalKwh, $rate) {
            $kwh     = $appliance->computeDailyKwh();
            $costNgn = round($kwh * $rate, 2);
            $pct     = $totalKwh > 0 ? round(($kwh / $totalKwh) * 100, 1) : 0;

            return [
                'appliance_id'     => $appliance->id,
                'name'             => $appliance->display_name,
                'icon'             => $appliance->applianceType?->icon_name,
                'quantity'         => $appliance->quantity,
                'wattage'          => $appliance->effectiveWattage(),
                'daily_hours'      => $appliance->is_always_on ? 24 : $appliance->daily_hours,
                'daily_kwh'        => $kwh,
                'daily_cost_ngn'   => $costNgn,
                'usage_percentage' => $pct,
            ];
        })->sortByDesc('daily_kwh')->values()->toArray();
    }

    /**
     * Recalculate and persist computed fields on all appliances for this meter.
     * Called after any appliance add/edit/delete or tariff band change.
     */
    public function recalculateAndPersist(): void
    {
        $rate     = $this->meter->tariffBand?->rate_per_kwh ?? 0;
        $totalKwh = $this->dailyKwh();

        foreach ($this->meter->appliances as $appliance) {
            $daily = $appliance->computeDailyKwh();
            $appliance->update([
                'daily_kwh'          => $daily,
                'daily_cost_ngn'     => round($daily * $rate, 2),
                'monthly_kwh'        => round($daily * 30, 3),
                'monthly_cost_ngn'   => round($daily * $rate * 30, 2),
                'usage_percentage'   => $totalKwh > 0 ? round(($daily / $totalKwh) * 100, 1) : 0,
            ]);
        }

        // Persist daily average to meter
        $this->meter->update([
            'daily_avg_consumption' => $this->dailyKwh(),
            'depletion_estimated_at' => $this->depletionAt(),
        ]);
    }

    /**
     * Generate a daily usage summary row for today (estimated, not IoT).
     * Called nightly by the GenerateWeeklyReport job.
     */
    public function upsertTodaySummary(): DailyUsageSummary
    {
        return DailyUsageSummary::updateOrCreate(
            ['meter_id' => $this->meter->id, 'summary_date' => today()->toDateString()],
            [
                'user_id'         => $this->meter->user_id,
                'kwh_consumed'    => $this->dailyKwh(),
                'cost_ngn'        => $this->dailyCostNgn(),
                'data_source'     => 'estimated',
            ]
        );
    }

    /**
     * Generate smart insight messages for the user (used by AnalyticsController).
     */
    public function insights(): array
    {
        $insights = [];
        $breakdown = $this->applianceBreakdown();

        // Top consumer insight
        if ($top = ($breakdown[0] ?? null)) {
            if ($top['usage_percentage'] > 35) {
                $insights[] = [
                    'type'    => 'high_consumer',
                    'icon'    => '💡',
                    'message' => "{$top['name']} is consuming {$top['usage_percentage']}% of your daily units. Reducing usage by 2 hours saves ₦" . round($top['wattage'] * 2 / 1000 * ($this->meter->tariffBand?->rate_per_kwh ?? 0), 0) . "/day.",
                ];
            }
        }

        // Low unit warning insight
        $days = $this->daysRemaining();
        if ($days !== null && $days < 3) {
            $insights[] = [
                'type'    => 'low_units',
                'icon'    => '⚠️',
                'message' => "At current usage, your units will run out in ~{$days} days. Recharge soon to avoid unexpected outage.",
            ];
        }

        // Monthly budget insight
        $monthly = $this->monthlyCostNgn();
        if ($monthly > 15000) {
            $insights[] = [
                'type'    => 'high_monthly',
                'icon'    => '📈',
                'message' => "Your estimated monthly electricity cost is ₦" . number_format($monthly) . ". Consider energy-saving habits to reduce this.",
            ];
        }

        return $insights;
    }
}
