<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Meter;
use App\Models\Appliance;
use App\Models\ApplianceType;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApplianceController extends ApiController
{
    /** GET /api/v1/appliance-types */
    public function types(): JsonResponse
    {
        $types = ApplianceType::active()
            ->get()
            ->groupBy('category')
            ->map(fn($group) => $group->map(fn($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug,
                'icon_name'   => $t->icon_name,
                'avg_wattage' => $t->avg_wattage,
                'is_always_on'=> $t->is_always_on,
            ]));

        return $this->successResponse("Fetched appliance types", ['categories' => $types]);
    }

    /** GET /api/v1/meters/{meter}/appliances */
    public function index(Meter $meter): JsonResponse
    {
        $this->authorize('view', $meter);
        $meter->load('appliances.applianceType', 'tariffBand');
        $estimator = new ConsumptionEstimator($meter);

        return $this->successResponse("Appliances data retrieved", [
            'appliances'      => $estimator->applianceBreakdown(),
            'total_daily_kwh' => $estimator->dailyKwh(),
            'total_daily_ngn' => $estimator->dailyCostNgn(),
            'monthly_est_ngn' => $estimator->monthlyCostNgn(),
        ]);
    }

    /** POST /api/v1/meters/{meter}/appliances */
    public function store(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('update', $meter);

        $data = $request->validate([
            'appliance_type_id' => 'required|exists:appliance_types,id',
            'nickname'          => 'nullable|string|max:80',
            'quantity'          => 'required|integer|min:1|max:20',
            'wattage'           => 'nullable|integer|min:1|max:50000',
            'daily_hours'       => 'required|numeric|min:0|max:24',
            'is_always_on'      => 'sometimes|boolean',
        ]);

        $appliance = Appliance::create(array_merge($data, [
            'meter_id' => $meter->id,
            'user_id'  => $request->user()->id,
        ]));

        // Recalculate all appliance estimates
        $estimator = new ConsumptionEstimator($meter->load('appliances.applianceType', 'tariffBand'));
        $estimator->recalculateAndPersist();

        return $this->successResponse("Appliance added", ['appliance' => $appliance->fresh('applianceType')]);
    }

    /** PATCH /api/v1/appliances/{appliance} */
    public function update(Request $request, Appliance $appliance): JsonResponse
    {
        if ($appliance->user_id !== $request->user()->id) abort(403);

        $data = $request->validate([
            'nickname'    => 'sometimes|string|max:80',
            'quantity'    => 'sometimes|integer|min:1|max:20',
            'wattage'     => 'nullable|integer|min:1',
            'daily_hours' => 'sometimes|numeric|min:0|max:24',
            'is_always_on'=> 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        $appliance->update($data);

        $estimator = new ConsumptionEstimator($appliance->meter->load('appliances.applianceType', 'tariffBand'));
        $estimator->recalculateAndPersist();

        return $this->ok('Appliance updated');
    }

    /** DELETE /api/v1/appliances/{appliance} */
    public function destroy(Request $request, Appliance $appliance): JsonResponse
    {
        if ($appliance->user_id !== $request->user()->id) abort(403);

        $meter = $appliance->meter->load('appliances.applianceType', 'tariffBand');
        $appliance->update(['is_active' => false]);

        $estimator = new ConsumptionEstimator($meter->load('appliances.applianceType', 'tariffBand'));
        $estimator->recalculateAndPersist();

        return $this->ok("Appliance removed");
    }
}
