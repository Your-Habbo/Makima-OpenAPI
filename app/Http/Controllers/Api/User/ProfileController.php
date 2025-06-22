<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * @group User Profile
     * 
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('profile', 'roles', 'permissions')),
        ]);
    }

    /**
     * @group User Profile
     * 
     * Get user profile
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'profile' => $request->user()->profile,
        ]);
    }

    /**
     * @group User Profile
     * 
     * Update user profile
     * 
     * @bodyParam first_name string optional First name. Example: John
     * @bodyParam last_name string optional Last name. Example: Doe
     * @bodyParam phone string optional Phone number. Example: +1234567890
     * @bodyParam date_of_birth string optional Date of birth (YYYY-MM-DD). Example: 1990-01-01
     * @bodyParam gender string optional Gender (male, female, other). Example: male
     * @bodyParam timezone string optional Timezone. Example: America/New_York
     * @bodyParam locale string optional Locale. Example: en
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Update user fields
        if ($request->has('phone')) {
            $user->update(['phone' => $request->phone]);
        }

        // Update or create profile
        $profileData = $request->only([
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'timezone',
            'locale'
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        return response()->json([
            'user' => new UserResource($user->fresh()->load('profile')),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * @group User Profile
     * 
     * Upload avatar
     * 
     * @bodyParam avatar file required Avatar image file. Max 2MB, jpg/png only.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar
        if ($user->profile?->avatar) {
            Storage::disk('public')->delete($user->profile->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['avatar' => $path]
        );

        return response()->json([
            'avatar_url' => Storage::url($path),
            'message' => 'Avatar uploaded successfully',
        ]);
    }
}