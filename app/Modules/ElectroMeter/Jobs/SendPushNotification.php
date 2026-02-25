<?php

namespace App\Modules\ElectroMeter\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public User   $user,
        public string $title,
        public string $body,
        public array  $data = [],
    ) {}

    public function handle(): void
    {
        if (!$this->user->fcm_token || !$this->user->notifications_enabled) return;

        $payload = [
            'to'           => $this->user->fcm_token,
            'notification' => [
                'title' => $this->title,
                'body'  => $this->body,
                'sound' => 'default',
            ],
            'data' => array_merge($this->data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]),
            'android' => [
                'priority' => $this->data['severity'] === 'critical' ? 'high' : 'normal',
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . config('services.fcm.server_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        if (!$response->successful()) {
            Log::error('FCM push notification failed', ['user' => $this->user->id, 'response' => $response->body()]);
        }
    }
}
