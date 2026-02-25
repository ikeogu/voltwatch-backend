<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\Estate;
use App\Models\EstateMember;
use App\Models\DailyUsageSummary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EstateController extends ApiController
{
    /** POST /api/v1/estates — landlord creates estate */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->subscription?->has_shared_meter) {
            return response()->json(['message' => 'Upgrade to Basic or higher to use Estate features.'], 403);
        }

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'address'     => 'required|string|max:255',
            'city'        => 'sometimes|string',
            'state'       => 'sometimes|string',
            'max_tenants' => 'sometimes|integer|min:2|max:50',
        ]);

        $estate = Estate::create(array_merge($data, ['owner_id' => $request->user()->id]));
        $request->user()->update(['role' => 'landlord']);

        return response()->json([
            'message'     => 'Estate created',
            'estate'      => $estate,
            'invite_code' => $estate->invite_code,
        ], 201);
    }

    /** POST /api/v1/estates/join — tenant joins via invite code */
    /**
     * Join an estate using an invite code. Validates the code, checks tenant limits, and associates the user with the estate and optionally a meter.
     * @param Request $request
     * @return JsonResponse
     */
    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invite_code' => 'required|string|exists:estates,invite_code',
            'flat_label'  => 'nullable|string|max:30',
            'meter_id'    => 'nullable|exists:meters,id',
        ]);

        $estate = Estate::where('invite_code', $data['invite_code'])->first();

        if ($estate->isFull()) {
            return $this->error("This estate has reached its tenant limit.", 422);
        }

        $existing = EstateMember::where('estate_id', $estate->id)->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return $this->error("You are already a member of this estate.", 422);
        }

        EstateMember::create([
            'estate_id'  => $estate->id,
            'user_id'    => $request->user()->id,
            'meter_id'   => $data['meter_id'] ?? null,
            'flat_label' => $data['flat_label'] ?? null,
            'status'     => 'active',
            'joined_at'  => now(),
        ]);

        return $this->ok("Joined {$estate->name} successfully");
    }

    /** GET /api/v1/estates/{estate}/dashboard — landlord view */
    /**
     * Dashboard data for an estate, showing all members, their meters, and usage summaries.
     * Only accessible by the estate owner (landlord).
     * Returns member details, current meter units, kWh consumed in last 30 days, and usage percentages.
     * @param Request $request
     * @param Estate $estate
     */
    public function dashboard(Request $request, Estate $estate): JsonResponse
    {
        if ($estate->owner_id !== $request->user()->id) abort(403);

        $members = $estate->members()->with('user', 'meter.dailySummaries')->get();

        $memberData = $members->map(function (EstateMember $m) {
            $meter = $m->meter;
            $kwh30d = $meter?->dailySummaries()
                ->where('summary_date', '>=', now()->subDays(30)->toDateString())
                ->sum('kwh_consumed') ?? 0;

            return [
                'member_id'   => $m->id,
                'flat_label'  => $m->flat_label ?? 'Unknown Unit',
                'name'        => $m->user?->name,
                'phone'       => $m->user?->phone,
                'role'        => $m->role,
                'meter_units' => $meter ? (float) $meter->current_units : null,
                'kwh_30d'     => round($kwh30d, 3),
                'days_remaining' => $meter?->estimatedDaysRemaining(),
            ];
        });

        // Estate totals
        $estateMeters = $estate->members()->with('meter')->get()->pluck('meter')->filter();
        $totalUnits   = $estateMeters->sum('current_units');
        $totalKwh30d  = $members->sum(fn($m) => $m->meter?->dailySummaries()
            ->where('summary_date', '>=', now()->subDays(30)->toDateString())
            ->sum('kwh_consumed') ?? 0);

        // Usage percentages
        $memberData = $memberData->map(function ($m) use ($totalKwh30d) {
            $m['usage_pct'] = $totalKwh30d > 0 ? round(($m['kwh_30d'] / $totalKwh30d) * 100, 1) : 0;
            return $m;
        })->sortByDesc('kwh_30d')->values();

        return $this->successResponse("Estate dashboard data retrieved", [
            'estate'        => ['id' => $estate->id, 'name' => $estate->name, 'address' => $estate->address],
            'totals'        => [
                'member_count'  => $members->count(),
                'total_units'   => round($totalUnits, 1),
                'total_kwh_30d' => round($totalKwh30d, 2),
            ],
            'members'       => $memberData,
            'invite_code'   => $estate->invite_code,
        ]);
    }
}
