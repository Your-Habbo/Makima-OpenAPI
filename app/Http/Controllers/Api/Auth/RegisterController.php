<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    /**
     * @group Authentication
     * 
     * Register new user
     * 
     * @bodyParam name string required User's full name. Example: John Doe
     * @bodyParam username string required Unique username. Example: johndoe
     * @bodyParam email string required User's email address. Example: john@example.com
     * @bodyParam password string required Password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Example: password123
     * @bodyParam device_name string required Device name for token. Example: iPhone 12
     * 
     * @response 201 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "username": "johndoe"
     *   },
     *   "token": "1|xxxxxxxxxxxxxxxxxxxx"
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            // Create user profile
            $nameParts = explode(' ', $request->name, 2);
            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
            ]);

            // Assign default role
            $user->assignRole('user');

            // Create token
            $token = $user->createToken($request->device_name)->plainTextToken;

            DB::commit();

            return response()->json([
                'user' => new UserResource($user->load('profile', 'roles')),
                'token' => $token,
                'message' => 'Registration successful',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}