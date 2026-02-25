<?php

namespace App\Modules\ElectroMeter\Services;

use App\Jobs\SendPushNotification;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Meter;
use App\Modules\ElectroMeter\Jobs\SendPushNotification as JobsSendPushNotification;
use Illuminate\Support\Facades\Log;


/**
 * AlertEvaluator
 *
 * Runs on a schedule (every 30 mins) and after each recharge/IoT reading.
 * Checks each alert rule against current meter state and fires notifications.
 */
class AlertEvaluator
{
    public function __construct(
        protected Meter $meter,
        protected ConsumptionEstimator $estimator,
    ) {}

    /**
     * Evaluate all active alerts for this meter.
     * Returns array of fired alert logs.
     */
    public function evaluate(): array
    {
        $fired = [];

        foreach ($this->meter->alerts as $alert) {
            if ($alert->isCoolingDown()) continue;

            $result = match ($alert->type) {
                'low_units'          => $this->checkLowUnits($alert),
                'high_daily_usage'   => $this->checkHighDailyUsage($alert),
                'estimated_depletion'=> $this->checkDepletionWarning($alert),
                'voltage_drop'       => $this->checkVoltageDrop($alert),
                'power_surge'        => $this->checkPowerSurge($alert),
                default              => null,
            };

            if ($result) {
                $log = $this->fireAlert($alert, $result['title'], $result['message'], $result['trigger_value']);
                $fired[] = $log;
            }
        }

        return $fired;
    }

    private function checkLowUnits(Alert $alert): ?array
    {
        $units = $this->meter->current_units;
        if ($units <= $alert->threshold_value) {
            return [
                'title'         => '🚨 Low Units Alert',
                'message'       => "Your meter has only {$units} kWh remaining — below your {$alert->threshold_value} kWh threshold. Recharge now!",
                'trigger_value' => $units,
            ];
        }
        return null;
    }

    private function checkHighDailyUsage(Alert $alert): ?array
    {
        $daily = $this->estimator->dailyKwh();
        if ($daily >= $alert->threshold_value) {
            return [
                'title'         => '⚡ High Daily Usage',
                'message'       => "Today's estimated usage is {$daily} kWh — above your {$alert->threshold_value} kWh daily limit.",
                'trigger_value' => $daily,
            ];
        }
        return null;
    }

    private function checkDepletionWarning(Alert $alert): ?array
    {
        $days = $this->estimator->daysRemaining();
        if ($days !== null && $days <= $alert->threshold_days) {
            $daysRounded = round($days, 1);
            return [
                'title'         => '📅 Units Running Low',
                'message'       => "Your units will run out in approximately {$daysRounded} days at current usage. Plan your recharge soon.",
                'trigger_value' => $days,
            ];
        }
        return null;
    }

    private function checkVoltageDrop(Alert $alert): ?array
    {
        $device = $this->meter->iotDevice;
        if (!$device || !$device->last_voltage) return null;

        if ($device->last_voltage < $alert->threshold_value) {
            return [
                'title'         => '⚠️ Voltage Drop Detected',
                'message'       => "Voltage dropped to {$device->last_voltage}V — below your {$alert->threshold_value}V threshold. Check your appliances.",
                'trigger_value' => $device->last_voltage,
            ];
        }
        return null;
    }

    private function checkPowerSurge(Alert $alert): ?array
    {
        $device = $this->meter->iotDevice;
        if (!$device || !$device->last_power_watts) return null;

        if ($device->last_power_watts > $alert->threshold_value) {
            return [
                'title'         => '🔴 Power Surge Alert',
                'message'       => "Current draw is {$device->last_power_watts}W — exceeds your {$alert->threshold_value}W limit. Disconnect heavy appliances.",
                'trigger_value' => $device->last_power_watts,
            ];
        }
        return null;
    }

    private function fireAlert(Alert $alert, string $title, string $message, float $triggerValue): AlertLog
    {
        $log = AlertLog::create([
            'alert_id'      => $alert->id,
            'user_id'       => $this->meter->user_id,
            'meter_id'      => $this->meter->id,
            'title'         => $title,
            'message'       => $message,
            'trigger_value' => $triggerValue,
            'severity'      => $alert->severity,
            'status'        => 'sent',
            'channels_used' => collect(['push' => $alert->notify_push, 'sms' => $alert->notify_sms])
                                ->filter()->keys()->toArray(),
        ]);

        $alert->markFired();

        // Dispatch push notification job
        if ($alert->notify_push && $this->meter->user->fcm_token) {
            JobsSendPushNotification::dispatch($this->meter->user, $title, $message, [
                'type'       => 'alert',
                'alert_id'   => $alert->id,
                'alert_type' => $alert->type,
                'severity'   => $alert->severity,
            ])->onQueue('notifications');
        }

        return $log;
    }
}
