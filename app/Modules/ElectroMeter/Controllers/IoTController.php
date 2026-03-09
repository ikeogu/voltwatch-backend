<?php

namespace App\Modules\ElectroMeter\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\IoTDevice;
use App\Models\Meter;
use App\Modules\ElectroMeter\Services\IoTBridgeService as ServicesIoTBridgeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IoTController extends ApiController
{

    public function __construct(
        public readonly ServicesIoTBridgeService $bridge
    ) {}
    /**
     * POST /api/v1/iot/devices/pair — pair a new IoT device to a meter
     * Validates device ID uniqueness, checks user subscription for IoT access, and creates a new IoTDevice record in 'pairing' status. Returns MQTT topic and pairing instructions.
     * @param Request $request
     * @return JsonResponse
     */

    public function pair(Request $request): JsonResponse
    {
        if (!$request->user()->canUseIoT()) {
            return $this->error('Upgrade to Pro plan to use IoT devices.', 403);
        }

        $data = $request->validate([
            'device_id'       => 'required|string|unique:iot_devices,device_id',
            'meter_id'        => 'required|exists:meters,id',
            'nickname'        => 'nullable|string|max:60',
            'connection_type' => 'in:wifi,gsm,ethernet',
        ]);

        $meter = Meter::findOrFail($data['meter_id']);
        if ($meter->user_id !== $request->user()->id) abort(403);

        $device = IoTDevice::create([
            'user_id'          => $request->user()->id,
            'meter_id'         => $meter->id,
            'device_id'        => $data['device_id'],
            'nickname'         => $data['nickname'] ?? 'VoltWatch Device',
            'connection_type'  => $data['connection_type'] ?? 'wifi',
            'status'           => 'pairing',
            'mqtt_topic'       => "voltwatch/device/{$data['device_id']}",
        ]);

        return $this->successResponse('Device pairing initiated', [
            'device'      => $device,
            'mqtt_topic'  => $device->mqtt_topic,
            'instructions' => 'Flash your device with this device_id and point it to: mqtt.voltwatchapp.com:1883',
        ]);
    }

    /** GET /api/v1/iot/devices — list user's IoT devices
     * Returns a list of the user's IoT devices with their status, last readings, and associated meter info. Used for device management dashboard.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->iotDevices()->with('meter')->get()->map(fn($d) => [
            'id'            => $d->id,
            'device_id'     => $d->device_id,
            'nickname'      => $d->nickname,
            'meter'         => $d->meter?->getNicknameOrDefault(),
            'status'        => $d->status,
            'is_online'     => $d->isOnline(),
            'last_voltage'  => $d->last_voltage,
            'last_current'  => $d->last_current,
            'last_power'    => $d->last_power_watts,
            'last_seen'     => $d->last_seen_at?->diffForHumans(),
            'firmware'      => $d->firmware_version,
        ]);

        return $this->successResponse('IoT devices retrieved', ['devices' => $devices]);
    }

    /**
     * GET /api/v1/iot/devices/{device}/live — real-time readings (polling fallback)
     * Returns recent readings from the specified IoT device for real-time monitoring. Validates device ownership and returns the last 30 readings from the past 5 minutes, along with current device status. Used as a polling fallback for live data display.
     * @param Request $request
     * @param IoTDevice $device
     * @return JsonResponse
     */
    public function live(Request $request, IoTDevice $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) abort(403);

        $recent = $device->usageReadings()
            ->where('recorded_at', '>=', now()->subMinutes(5))
            ->latest('recorded_at')
            ->take(30)
            ->get(['voltage', 'current', 'power_watts', 'recorded_at', 'supply_status']);

        return $this->successResponse('Live readings retrieved', [
            'device'         => [
                'is_online'    => $device->isOnline(),
                'voltage'      => $device->last_voltage,
                'current'      => $device->last_current,
                'power_watts'  => $device->last_power_watts,
                'power_factor' => $device->last_power_factor,
                'frequency'    => $device->last_frequency,
                'last_seen'    => $device->last_seen_at?->toIso8601String(),
            ],
            'readings'       => $recent,
        ]);
    }

    /** POST /api/v1/iot/ingest — called by MQTT bridge to ingest a reading (internal/signed)
     * This endpoint is for internal use by the MQTT bridge service. It validates the incoming reading payload, checks the device ID, and dispatches the data for processing. It should be protected by a shared secret and not exposed to regular users.
     * @param Request $request
     */
    public function ingest(Request $request): JsonResponse
    {
        // This endpoint should be protected by a shared secret, not Sanctum
        $secret = $request->header('X-IoT-Secret');
        if ($secret !== config('services.iot.ingest_secret')) {
            return $this->error('Unauthorized', 401);
        }

        $data = $request->validate([
            'device_id' => 'required|string',
            'payload'   => 'required|string', // raw JSON from device
        ]);

        $this->bridge->handleReading($data['device_id'], $data['payload']);

        return $this->successResponse('Reading ingested', ['ok' => true]);
    }
}