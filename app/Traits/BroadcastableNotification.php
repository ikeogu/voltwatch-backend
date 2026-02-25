<?php

namespace App\Traits;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

trait BroadcastableNotification
{
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->getUserId())];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->title,
            'body'  => $this->body,
        ]);
    }

    public function broadcastAs(): string
    {
        return $this->eventName ?? class_basename(static::class);
    }

    protected function getUserId(): int|string
    {
        return $this->userId ?? $this->notifiable->id ?? null;
    }
}
