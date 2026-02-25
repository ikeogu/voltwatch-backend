<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\ApiController;
use App\Models\User;
use App\Models\Subscription;
use App\Modules\Shared\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /**
     * POST /api/v1/auth/register
     * Registers a new user and auto-creates a free trial subscription.
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|unique:users,phone|regex:/^(\+234|0)[789][01]\d{8}$/',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'],
            'password' => Hash::make($data['password']),
        ]);

        // Auto-create 7-day free trial subscription
        Subscription::defaultFreeFor($user->id);

        $token = $user->createToken('voltwatch-mobile', ['*'])->plainTextToken;

        return $this->successResponse('Account created successfully',[
            'token'   => $token,
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/login
     * Allows users to log in using either email or phone number.
     * @param Request $request
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'    => 'required|string',       // email or phone
            'password' => 'required|string',
        ]);

        // Allow login by email OR phone
        $user = User::where('email', $data['login'])
                    ->orWhere('phone', $data['login'])
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke old tokens (single device policy — optional, remove for multi-device)
        $user->tokens()->delete();

        $token = $user->createToken('voltwatch-mobile', ['*'])->plainTextToken;

        $user->update(['last_active_at' => now()]);

        return $this->successResponse('Login successful', [
            'token'   => $token,
            'user'    => new UserResource($user->load('subscription', 'primaryMeter.tariffBand')),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Logs out the user by revoking the current access token.
     * @param Request $request
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse('Logged out successfully');
    }

    /**
     * GET /api/v1/auth/me
     * Returns the authenticated user's details along with subscription and primary meter info.
     * @param Request $request
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscription', 'primaryMeter.tariffBand', 'primaryMeter.appliances');
        return $this->successResponse('User retrieved', ['user' => new UserResource($user)]);
    }

    /**
     * PATCH /api/v1/auth/me
     * Allows the authenticated user to update their profile information, including name, timezone, and notification preferences.
     * @param Request $request
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'sometimes|string|max:100',
            'fcm_token'             => 'sometimes|string',
            'timezone'              => 'sometimes|timezone',
            'notifications_enabled' => 'sometimes|boolean',
        ]);

        $request->user()->update($data);

        return $this->successResponse('Profile updated', [
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }

    /**
     * POST /api/v1/auth/change-password
     * Allows the authenticated user to change their password by providing the current password and a new password (with confirmation).
     * @param Request $request
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Incorrect current password.']]);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return $this->successResponse('Password changed successfully', [
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }

}
