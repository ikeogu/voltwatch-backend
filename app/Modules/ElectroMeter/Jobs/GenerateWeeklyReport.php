<?php

namespace App\Modules\ElectroMeter\Jobs;

use App\Models\Meter;
use App\Models\DailyUsageSummary;
use App\Modules\ElectroMeter\Services\AlertEvaluator;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * GenerateWeeklyReport
 *
 * Runs nightly at midnight (Africa/Lagos) via Laravel Scheduler.
 * For each active meter:
 *   1. Upsert today's estimated daily summary (for app-only users)
 *   2. Recalculate rolling 7-day average consumption
 *   3. Update depletion estimate
 *   4. Evaluate alert rules
 *
 * Scheduled in: routes/console.php or App\Console\Kernel
 *   Schedule::job(new GenerateWeeklyReport)->dailyAt('00:05')->timezone('Africa/Lagos');
 */
class GenerateWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Meter::where('is_active', true)
            ->with('appliances.applianceType', 'tariffBand', 'alerts', 'iotDevice')
            ->chunk(50, function ($meters) {
                foreach ($meters as $meter) {
                    $estimator = new ConsumptionEstimator($meter);

                    // 1. Upsert today's summary for estimate-based users (IoT users get real data from ProcessIoTReading)
                    if (!$meter->iotDevice || !$meter->iotDevice->isOnline()) {
                        $estimator->upsertTodaySummary();
                    }

                    // 2. Recalculate rolling average from last 7 real days
                    $meter->recalculateDailyAverage();

                    // 3. Evaluate all alerts for this meter
                    if ($meter->alerts->count() > 0) {
                        $meter->refresh()->load('alerts');
                        (new AlertEvaluator($meter, $estimator))->evaluate();
                    }
                }
            });
    }
}
