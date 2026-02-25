<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\ApiController;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends ApiController
{
    /**
     * GET /api/v1/dashboard
     *
     * Single endpoint for the entire home screen.
     * Returns everything the React Native dashboard needs in one call.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $meter = $user->primaryMeter?->load('tariffBand', 'appliances.applianceType', 'iotDevice');

        if (!$meter) {
            return $this->errorWithData(
                "Complete your meter setup to get started.",
                Response::HTTP_BAD_REQUEST,
                [
                    'has_meter'   => false,
                    'setup_required' => true,
                ]
            );
        }

        $estimator = new ConsumptionEstimator($meter);

        // Last recharge
        $lastRecharge = $meter->recharges()->first();

        // Recent alert logs (unread)
        $unreadAlerts = $user->alertLogs()
            ->where('status', 'sent')
            ->latest()
            ->take(5)
            ->get();

        // Appliance breakdown (top consumers)
        $breakdown = $estimator->applianceBreakdown();

        // IoT live data (if device connected)
        $liveData = null;
        if ($meter->iotDevice && $meter->iotDevice->isOnline()) {
            $device   = $meter->iotDevice;
            $liveData = [
                'is_online'    => true,
                'voltage'      => $device->last_voltage,
                'current'      => $device->last_current,
                'power_watts'  => $device->last_power_watts,
                'power_factor' => $device->last_power_factor,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ];
        }

        // Weekly sparkline (last 7 days usage)
        $weeklyData = $meter->dailySummaries()
            ->where('summary_date', '>=', now()->subDays(6)->toDateString())
            ->orderBy('summary_date')
            ->get(['summary_date', 'kwh_consumed', 'cost_ngn', 'data_source'])
            ->map(fn($s) => [
                'date'         => $s->summary_date->format('D'),
                'kwh'          => (float) $s->kwh_consumed,
                'cost_ngn'     => (float) $s->cost_ngn,
                'data_source'  => $s->data_source,
            ]);

        return $this->successResponse("Dashboard data retrieved", [
            'has_meter'    => true,
            'meter'        => [
                'id'                     => $meter->id,
                'nickname'               => $meter->getNicknameOrDefault(),
                'meter_number'           => '****' . substr($meter->meter_number, -4),
                'type'                   => $meter->type,
                'disco'                  => $meter->disco,
            ],
            'units'        => [
                'current'                => (float) $meter->current_units,
                'daily_avg'              => (float) $meter->daily_avg_consumption,
                'days_remaining'         => $estimator->daysRemaining(),
                'depletion_at'           => $estimator->depletionAt()?->toIso8601String(),
                'is_critically_low'      => $meter->isCriticallyLow(),
                'units_last_updated_at'  => $meter->units_last_updated_at?->toIso8601String(),
            ],
            'costs'        => [
                'tariff_band'            => $meter->tariffBand?->code,
                'rate_per_kwh'           => (float) $meter->tariffBand?->rate_per_kwh,
                'daily_cost_ngn'         => $estimator->dailyCostNgn(),
                'monthly_estimate_ngn'   => $estimator->monthlyCostNgn(),
            ],
            'last_recharge' => $lastRecharge ? [
                'units_added'    => (float) $lastRecharge->units_added,
                'amount_paid'    => (float) $lastRecharge->amount_paid,
                'recharged_at'   => $lastRecharge->recharged_at->toDateString(),
            ] : null,
            'top_consumers'  => array_slice($breakdown, 0, 4),
            'unread_alert_count' => $unreadAlerts->count(),
            'alerts_preview'     => $unreadAlerts->map(fn($a) => [
                'id'       => $a->id,
                'title'    => $a->title,
                'message'  => $a->message,
                'severity' => $a->severity,
                'time'     => $a->created_at->diffForHumans(),
            ]),
            'weekly_usage'   => $weeklyData,
            'live_data'      => $liveData,
            'insights'       => $estimator->insights(),
        ]);
    }
}
