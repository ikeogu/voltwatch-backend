<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Meter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AlertController extends ApiController
{
    /** GET /api/v1/meters/{meter}/alerts — alert rules */
    public function rules(Meter $meter): JsonResponse
    {
        $this->authorize('view', $meter);
        return response()->json(['alerts' => $meter->alerts()->get()]);
    }

    /** POST /api/v1/meters/{meter}/alerts */
    public function store(Request $request, Meter $meter): JsonResponse
    {
        $this->authorize('update', $meter);
        $data = $request->validate([
            'type'              => 'required|in:low_units,high_daily_usage,estimated_depletion,voltage_drop,power_surge,recharge_reminder',
            'threshold_value'   => 'nullable|numeric',
            'threshold_days'    => 'nullable|integer|min:1|max:30',
            'severity'          => 'in:info,warning,critical',
            'notify_push'       => 'boolean',
            'notify_sms'        => 'boolean',
            'cooldown_minutes'  => 'integer|min:30|max:1440',
        ]);

        $alert = $meter->alerts()->create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json(['message' => 'Alert rule created', 'alert' => $alert], 201);
    }

    /** PATCH /api/v1/alerts/{alert} */
    public function update(Request $request, Alert $alert): JsonResponse
    {
        if ($alert->user_id !== $request->user()->id) abort(403);
        $alert->update($request->only(['threshold_value','threshold_days','severity','notify_push','notify_sms','is_active','cooldown_minutes']));
        return response()->json(['message' => 'Alert updated', 'alert' => $alert]);
    }

    /** DELETE /api/v1/alerts/{alert} */
    public function destroy(Request $request, Alert $alert): JsonResponse
    {
        if ($alert->user_id !== $request->user()->id) abort(403);
        $alert->delete();
        return response()->json(['message' => 'Alert rule deleted']);
    }

    /** GET /api/v1/notifications — fired alert history */
    public function logs(Request $request): JsonResponse
    {
        $logs = $request->user()->alertLogs()->with('meter')->paginate(25);
        return response()->json([
            'notifications' => $logs->map(fn($l) => [
                'id'       => $l->id,
                'title'    => $l->title,
                'message'  => $l->message,
                'severity' => $l->severity,
                'status'   => $l->status,
                'meter'    => $l->meter?->getNicknameOrDefault(),
                'time'     => $l->created_at->diffForHumans(),
                'read_at'  => $l->read_at?->toIso8601String(),
            ]),
            'unread_count' => $request->user()->alertLogs()->where('status','sent')->count(),
        ]);
    }

    /** POST /api/v1/notifications/{log}/read */
    public function markRead(Request $request, AlertLog $log): JsonResponse
    {
        if ($log->user_id !== $request->user()->id) abort(403);
        $log->markRead();
        return response()->json(['message' => 'Marked as read']);
    }

    /** POST /api/v1/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->alertLogs()->where('status','sent')->update(['status'=>'read','read_at'=>now()]);
        return response()->json(['message' => 'All notifications marked as read']);
    }
}
