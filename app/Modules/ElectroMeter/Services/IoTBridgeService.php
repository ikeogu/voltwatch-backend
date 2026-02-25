<?php

namespace App\Modules\ElectroMeter\Services;

use App\Models\IoTDevice;
use App\Models\UsageReading;
use App\Modules\ElectroMeter\Jobs\ProcessIoTReading;
use Illuminate\Support\Facades\Log;

/**
 * IoTBridgeService
 *
 * Handles incoming MQTT messages from VoltWatch hardware devices.
 * Works with php-mqtt/laravel-mqtt package.
 *
 * MQTT Topic structure:
 *   voltwatch/device/{device_id}/readings   → Sensor data (voltage, current, watts)
 *   voltwatch/device/{device_id}/status     → Online/offline heartbeat
 *   voltwatch/device/{device_id}/config     → OTA config push (server → device)
 *
 * Device payload example (JSON):
 * {
 *   "v": 224.5,        // voltage (V)
 *   "i": 6.842,        // current (A)
 *   "p": 1528.6,       // active power (W)
 *   "pf": 0.994,       // power factor
 *   "hz": 49.98,       // frequency (Hz)
 *   "e": 0.004246,     // energy this interval (kWh)
 *   "ts": 1708694400   // Unix timestamp from device RTC
 * }
 */
class IoTBridgeService
{
    /**
     * Process an incoming MQTT reading message.
     * Validates, then dispatches to queue for heavy processing.
     */
    public function handleReading(string $deviceId, string $rawPayload): void
    {
        $device = IoTDevice::where('device_id', $deviceId)->with('meter.tariffBand')->first();

        if (!$device) {
            Log::warning("IoT reading from unknown device: {$deviceId}");
            return;
        }

        $data = json_decode($rawPayload, true);
        if (!$this->validatePayload($data)) {
            Log::warning("Invalid IoT payload from device {$deviceId}", ['payload' => $rawPayload]);
            return;
        }

        // Update device snapshot immediately (non-queued for real-time display)
        $device->updateFromReading([
            'voltage'      => $data['v'] ?? null,
            'current'      => $data['i'] ?? null,
            'power_watts'  => $data['p'] ?? null,
            'power_factor' => $data['pf'] ?? null,
            'frequency'    => $data['hz'] ?? null,
        ]);

        // Queue the heavy processing (write reading row, update summaries, evaluate alerts)
        ProcessIoTReading::dispatch($device->id, $data)->onQueue('iot');
    }

    /**
     * Handle device status heartbeat (online/offline).
     */
    public function handleStatus(string $deviceId, string $rawPayload): void
    {
        $data   = json_decode($rawPayload, true);
        $status = $data['status'] ?? 'offline';

        IoTDevice::where('device_id', $deviceId)->update([
            'status'       => $status,
            'last_seen_at' => now(),
            'firmware_version' => $data['fw'] ?? null,
        ]);
    }

    /**
     * Build and publish a config command to a device via MQTT.
     * E.g., change reporting interval, trigger OTA update.
     */
    public function publishConfig(IoTDevice $device, array $config): void
    {
        // Use the MQTT client to publish
        $topic   = "voltwatch/device/{$device->device_id}/config";
        $payload = json_encode($config);

        // This requires MQTT client — see config/mqtt.php for broker settings
        // app('mqtt')->publish($topic, $payload);

        $device->update(['config' => array_merge($device->config ?? [], $config)]);
    }

    private function validatePayload(?array $data): bool
    {
        return $data !== null && (isset($data['v']) || isset($data['p']));
    }
}
