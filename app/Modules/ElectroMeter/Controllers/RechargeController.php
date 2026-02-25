<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Meter;
use App\Models\Recharge;
use App\Modules\ElectroMeter\Services\AlertEvaluator;
use App\Modules\ElectroMeter\Services\ConsumptionEstimator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RechargeController extends ApiController
{
    use AuthorizesRequests;
    /** GET /api/v1/meters/{meter}/recharges */
    public function index(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('view', $meter);

        $recharges = $meter->recharges()
            ->latest('recharged_at')
            ->paginate(20);

        $summary = [
            'total_units_bought'  => $meter->recharges()->sum('units_added'),
            'total_spent_ngn'     => $meter->recharges()->sum('amount_paid'),
            'this_month_spent'    => $meter->recharges()
                ->whereMonth('recharged_at', now()->month)
                ->sum('amount_paid'),
            'recharge_count'      => $meter->recharges()->count(),
        ];

        return response()->json([
            'recharges' => $recharges->map(fn($r) => $this->rechargePayload($r)),
            'summary'   => $summary,
            'pagination'=> ['current_page' => $recharges->currentPage(), 'last_page' => $recharges->lastPage()],
        ]);
    }

    /** POST /api/v1/meters/{meter}/recharges */
    public function store(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('update', $meter);

        $data = $request->validate([
            'units_added'    => 'required|numeric|min:0.001|max:9999',
            'amount_paid'    => 'required|numeric|min:1',
            'token_number'   => 'nullable|string|max:60',
            'recharged_at'   => 'required|date|before_or_equal:today',
            'notes'          => 'nullable|string|max:255',
        ]);

        $unitsBefore = $meter->current_units;
        $unitsAfter  = $unitsBefore + $data['units_added'];

        $recharge = Recharge::create([
            'meter_id'      => $meter->id,
            'user_id'       => $request->user()->id,
            'units_added'   => $data['units_added'],
            'amount_paid'   => $data['amount_paid'],
            'rate_at_time'  => $meter->tariffBand?->rate_per_kwh ?? 0,
            'units_before'  => $unitsBefore,
            'units_after'   => $unitsAfter,
            'token_number'  => $data['token_number'] ?? null,
            'recharged_at'  => $data['recharged_at'],
            'source'        => 'manual',
            'notes'         => $data['notes'] ?? null,
        ]);

        // Update meter balance
        $meter->update([
            'current_units'          => $unitsAfter,
            'units_last_updated_at'  => now(),
        ]);

        // Recalculate depletion estimate
        $estimator = new ConsumptionEstimator($meter->load('appliances.applianceType', 'tariffBand'));
        $meter->update(['depletion_estimated_at' => $estimator->depletionAt()]);

        // Re-evaluate alerts (units are now higher, may clear some warnings)
        $evaluator = new AlertEvaluator($meter->fresh('alerts', 'iotDevice'), $estimator);
        $evaluator->evaluate();

        return response()->json([
            'message'       => 'Recharge logged successfully',
            'recharge'      => $this->rechargePayload($recharge),
            'new_balance'   => (float) $unitsAfter,
            'days_remaining'=> $estimator->daysRemaining(),
        ], 201);
    }

    /** DELETE /api/v1/recharges/{recharge} */
    public function destroy(Request $request, Recharge $recharge): JsonResponse
    {
        if ($recharge->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Reverse the balance impact
        $meter = $recharge->meter;
        $meter->update(['current_units' => max(0, $meter->current_units - $recharge->units_added)]);
        $recharge->delete();

        return response()->json(['message' => 'Recharge entry deleted']);
    }

    private function rechargePayload(Recharge $recharge): array
    {
        return [
            'id'            => $recharge->id,
            'units_added'   => (float) $recharge->units_added,
            'amount_paid'   => (float) $recharge->amount_paid,
            'cost_per_unit' => $recharge->costPerUnit(),
            'masked_token'  => $recharge->masked_token,
            'source'        => $recharge->source,
            'notes'         => $recharge->notes,
            'recharged_at'  => $recharge->recharged_at->toDateString(),
            'created_at'    => $recharge->created_at->toIso8601String(),
        ];
    }
}
