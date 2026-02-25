<?php

namespace App\Modules\ElectroMeter\Jobs;

use App\Models\IoTDevice;
use App\Models\UsageReading;
use App\Models\DailyUsageSummary;
use App\Modules\ElectroMeter\Services\AlertEvaluator;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * ProcessIoTReading — queued job (runs on 'iot' queue)
 *
 * Handles the heavy lifting after an IoT reading arrives:
 * 1. Persist the raw reading row
 * 2. Update the meter's current_units based on energy delta
 * 3. Update today's daily usage summary
 * 4. Evaluate alert rules
 * 5. Broadcast to WebSocket channel for real-time app update
 */
class ProcessIoTReading implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int   $deviceId,
        public array $data,
    ) {}

    public function handle(): void
    {
        $device = IoTDevice::find($this->deviceId)?->load('meter.tariffBand');
        if (!$device) return;

        $meter = $device->meter;
        $rate  = $meter->tariffBand?->rate_per_kwh ?? 0;
        $kwh   = $this->data['e'] ?? 0; // energy for this interval
        $ts    = isset($this->data['ts']) ? \Carbon\Carbon::createFromTimestamp($this->data['ts']) : now();

        // 1. Persist reading
        UsageReading::create([
            'iot_device_id' => $device->id,
            'meter_id'      => $meter->id,
            'voltage'       => $this->data['v'] ?? null,
            'current'       => $this->data['i'] ?? null,
            'power_watts'   => $this->data['p'] ?? null,
            'power_factor'  => $this->data['pf'] ?? null,
            'frequency'     => $this->data['hz'] ?? null,
            'energy_kwh'    => $kwh,
            'cost_ngn'      => round($kwh * $rate, 6),
            'supply_status' => ($this->data['p'] ?? 0) > 0 ? 'on' : 'off',
            'recorded_at'   => $ts,
        ]);

        // 2. Decrement meter units
        if ($kwh > 0) {
            $meter->decrement('current_units', $kwh);
            $meter->update(['units_last_updated_at' => now()]);
        }

        // 3. Update today's summary
        DailyUsageSummary::updateOrCreate(
            ['meter_id' => $meter->id, 'summary_date' => today()->toDateString()],
            function ($s) use ($kwh, $rate, $meter) {
                $readings = $meter->usageReadings()
                    ->whereDate('recorded_at', today())
                    ->selectRaw('MAX(power_watts) as peak, AVG(power_watts) as avg_w, MIN(voltage) as min_v, MAX(voltage) as max_v, COUNT(*) as supply_cnt')
                    ->first();

                return [
                    'user_id'        => $meter->user_id,
                    'kwh_consumed'   => DB::raw("kwh_consumed + {$kwh}"),
                    'cost_ngn'       => DB::raw("cost_ngn + " . round($kwh * $rate, 4)),
                    'peak_wattage'   => $readings?->peak,
                    'avg_wattage'    => $readings?->avg_w,
                    'min_voltage'    => $readings?->min_v,
                    'max_voltage'    => $readings?->max_v,
                    'supply_minutes' => $readings?->supply_cnt * ($device->reading_interval_seconds / 60),
                    'data_source'    => 'iot',
                ];
            }
        );

        // 4. Evaluate alerts (every 10 readings ~ every 100s)
        if (rand(1, 10) === 1) {
            $meter->refresh()->load('alerts', 'iotDevice', 'tariffBand', 'appliances.applianceType');
            $estimator = new ConsumptionEstimator($meter);
            (new AlertEvaluator($meter, $estimator))->evaluate();
        }

        // 5. Broadcast to WebSocket (Laravel Reverb)
        // broadcast(new \App\Events\IoTReadingReceived($device, $this->data));
    }
}
