<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Meter;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AnalyticsController extends ApiController
{
    use AuthorizesRequests;
    /**
     * GET /api/v1/meters/{meter}/analytics
     * Query params: period=week|month|3months|year
     */
    public function index(Request $request, Meter $meter): JsonResponse
    {
        //$this->authorize('view', $meter);

        $period = $request->query('period', 'week');
        [$startDate, $groupBy] = $this->periodConfig($period);

        $meter->load('tariffBand', 'appliances.applianceType');
        $estimator = new ConsumptionEstimator($meter);

        // Daily summaries for the period
        $summaries = $meter->dailySummaries()
            ->where('summary_date', '>=', $startDate)
            ->orderBy('summary_date')
            ->get();

        // Fill missing days with estimated values
        $chartData = $this->fillChartData($summaries, $startDate, $estimator->dailyKwh());

        // Aggregates
        $totalKwh  = $summaries->sum('kwh_consumed');
        $totalNgn  = $summaries->sum('cost_ngn');
        $avgKwh    = $summaries->count() ? round($totalKwh / $summaries->count(), 3) : $estimator->dailyKwh();
        $peakDay   = $summaries->sortByDesc('kwh_consumed')->first();

        // Month-over-month comparison
        $prevStart = Carbon::parse($startDate)->sub($this->periodDuration($period));
        $prevTotal = $meter->dailySummaries()
            ->whereBetween('summary_date', [$prevStart, $startDate])
            ->sum('kwh_consumed');
        $change = $prevTotal > 0 ? round((($totalKwh - $prevTotal) / $prevTotal) * 100, 1) : null;

        return $this->successResponse("Analytics data retrieved", [
            'period'            => $period,
            'chart_data'        => $chartData,
            'summary'           => [
                'total_kwh'         => round($totalKwh, 3),
                'total_cost_ngn'    => round($totalNgn, 2),
                'avg_daily_kwh'     => round($avgKwh, 3),
                'avg_daily_cost'    => round($avgKwh * ($meter->tariffBand?->rate_per_kwh ?? 0), 2),
                'peak_day'          => $peakDay ? ['date' => $peakDay->summary_date->format('D, M j'), 'kwh' => (float)$peakDay->kwh_consumed] : null,
                'change_pct'        => $change,
                'change_direction'  => $change ? ($change > 0 ? 'up' : 'down') : null,
            ],
            'appliance_breakdown' => $estimator->applianceBreakdown(),
            'insights'           => $estimator->insights(),
            'tariff_info'        => [
                'band'        => $meter->tariffBand?->code,
                'rate_kwh'    => $meter->tariffBand?->rate_per_kwh,
            ],
        ]);
    }

    private function periodConfig(string $period): array
    {
        return match ($period) {
            'month'   => [now()->subDays(29)->toDateString(), 'day'],
            '3months' => [now()->subDays(89)->toDateString(), 'week'],
            'year'    => [now()->subDays(364)->toDateString(), 'month'],
            default   => [now()->subDays(6)->toDateString(), 'day'],   // week
        };
    }

    private function periodDuration(string $period): string
    {
        return match ($period) {
            'month'   => '30 days',
            '3months' => '90 days',
            'year'    => '365 days',
            default   => '7 days',
        };
    }

    private function fillChartData($summaries, string $startDate, float $estimatedDaily): array
    {
        $indexed = $summaries->keyBy(fn($s) => $s->summary_date->toDateString());
        $result  = [];

        $period = CarbonPeriod::create($startDate, today());
        foreach ($period as $date) {
            $key     = $date->toDateString();
            $summary = $indexed->get($key);
            $result[] = [
                'date'        => $date->format('D'),
                'full_date'   => $key,
                'kwh'         => $summary ? (float) $summary->kwh_consumed : $estimatedDaily,
                'cost_ngn'    => $summary ? (float) $summary->cost_ngn : 0,
                'is_estimated'=> !$summary,
                'data_source' => $summary?->data_source ?? 'estimated',
            ];
        }

        return $result;
    }
}