<?php

namespace App\Modules\Shared\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'role'                   => $this->role,
            'avatar_url'             => $this->avatar_url,
            'timezone'               => $this->timezone,
            'notifications_enabled'  => $this->notifications_enabled,
            'plan'                   => $this->getActivePlan(),
            'subscription'           => $this->subscription ? [
                'plan'                 => $this->subscription->plan,
                'status'               => $this->subscription->status,
                'is_on_trial'          => $this->subscription->isOnTrial(),
                'trial_ends_at'        => $this->subscription->trial_ends_at?->toIso8601String(),
                'current_period_end'   => $this->subscription->current_period_end?->toIso8601String(),
                'has_iot'              => $this->subscription->max_iot_devices > 0,
                'has_shared_meter'     => $this->subscription->has_shared_meter,
                'has_ai_insights'      => $this->subscription->has_ai_insights,
            ] : null,
            'has_meter'              => $this->primaryMeter !== null,
        ];
    }
}
