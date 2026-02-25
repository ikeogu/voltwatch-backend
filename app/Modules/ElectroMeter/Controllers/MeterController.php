<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Meter;
use App\Models\TariffBand;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MeterController extends ApiController
{
    /** GET /api/v1/meters */
    public function index(Request $request): JsonResponse
    {
        $meters = $request->user()->meters()->with('tariffBand')->where('is_active', true)->get();
        return response()->json(['meters' => $meters->map(fn($m) => $this->meterPayload($m))]);
    }

    /** POST /api/v1/meters */
    public function store(Request $request): JsonResponse
    {
        $sub = $request->user()->subscription;
        $currentCount = $request->user()->meters()->where('is_active', true)->count();

        if ($currentCount >= ($sub?->max_meters ?? 1)) {
            return response()->json(['message' => 'Upgrade your plan to add more meters.'], 403);
        }

        $data = $request->validate([
            'meter_number'    => 'required|string|unique:meters,meter_number',
            'nickname'        => 'nullable|string|max:60',
            'type'            => 'required|in:prepaid,postpaid,shared',
            'tariff_band_id'  => 'required|exists:tariff_bands,id',
            'disco'           => 'nullable|string',
            'current_units'   => 'required|numeric|min:0|max:9999',
            'tenant_count'    => 'sometimes|integer|min:1|max:50',
        ]);

        // Unset any existing primary meter
        if ($currentCount === 0) {
            $data['is_primary'] = true;
        }

        $meter = $request->user()->meters()->create($data);

        // Create default low-unit alert
        $meter->alerts()->create([
            'user_id'          => $request->user()->id,
            'type'             => 'low_units',
            'threshold_value'  => 20,
            'severity'         => 'warning',
            'notify_push'      => true,
            'cooldown_minutes' => 120,
        ]);

        return response()->json(['message' => 'Meter added', 'meter' => $this->meterPayload($meter->load('tariffBand'))], 201);
    }

    /** PATCH /api/v1/meters/{meter} */
    public function update(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('update', $meter);

        $data = $request->validate([
            'nickname'        => 'sometimes|string|max:60',
            'tariff_band_id'  => 'sometimes|exists:tariff_bands,id',
            'current_units'   => 'sometimes|numeric|min:0|max:9999',
            'tenant_count'    => 'sometimes|integer|min:1',
        ]);

        $meter->update($data);

        // Recalculate estimates if tariff or units changed
        if (isset($data['tariff_band_id']) || isset($data['current_units'])) {
            $estimator = new ConsumptionEstimator($meter->load('appliances.applianceType', 'tariffBand'));
            $estimator->recalculateAndPersist();
        }

        return response()->json(['message' => 'Meter updated', 'meter' => $this->meterPayload($meter->fresh('tariffBand'))]);
    }

    /** DELETE /api/v1/meters/{meter} */
    public function destroy(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('delete', $meter);
        $meter->update(['is_active' => false]);
        return response()->json(['message' => 'Meter removed']);
    }

    /** GET /api/v1/tariff-bands */
    public function tariffBands(): JsonResponse
    {
        return response()->json(['tariff_bands' => TariffBand::active()->get()]);
    }

    private function meterPayload(Meter $meter): array
    {
        $estimator = new ConsumptionEstimator($meter);
        return [
            'id'               => $meter->id,
            'nickname'         => $meter->getNicknameOrDefault(),
            'meter_number'     => '****' . substr($meter->meter_number, -4),
            'type'             => $meter->type,
            'disco'            => $meter->disco,
            'is_primary'       => $meter->is_primary,
            'current_units'    => (float) $meter->current_units,
            'days_remaining'   => $estimator->daysRemaining(),
            'daily_cost_ngn'   => $estimator->dailyCostNgn(),
            'tariff_band'      => ['code' => $meter->tariffBand?->code, 'rate' => $meter->tariffBand?->rate_per_kwh],
            'is_critically_low'=> $meter->isCriticallyLow(),
        ];
    }
}
