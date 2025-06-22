<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.view')->only(['index', 'show']);
        $this->middleware('permission:users.create')->only(['store']);
        $this->middleware('permission:users.edit')->only(['update']);
        $this->middleware('permission:users.delete')->only(['destroy']);
    }

    /**
     * @group Admin - User Management
     * 
     * List all users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profile', 'roles']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->input('role'));
            });
        }

        $users = $query->paginate(15);

        return response()->json([
            'users' => UserResource::collection($users),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * @group Admin - User Management
     * 
     * Create new user
     * 
     * @bodyParam name string required User's full name. Example: Jane Smith
     * @bodyParam username string required Unique username. Example: janesmith
     * @bodyParam email string required User's email address. Example: jane@example.com
     * @bodyParam password string required Password (min 8 characters). Example: password123
     * @bodyParam phone string optional Phone number. Example: +1234567890
     * @bodyParam is_active boolean optional User active status. Example: true
     * @bodyParam roles array optional Array of role names. Example: ["user"]
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|min:3|max:50|regex:/^[a-zA-Z0-9._-]+$/',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Create user profile
            $nameParts = explode(' ', $request->name, 2);
            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
            ]);

            // Assign roles
            if ($request->has('roles')) {
                $user->assignRole($request->input('roles'));
            } else {
                $user->assignRole('user'); // Default role
            }

            DB::commit();

            return response()->json([
                'user' => new UserResource($user->load('profile', 'roles')),
                'message' => 'User created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'User creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @group Admin - User Management
     * 
     * Show user details
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user->load('profile', 'roles', 'permissions')),
        ]);
    }

    /**
     * @group Admin - User Management
     * 
     * Update user
     * 
     * @bodyParam name string optional User's name. Example: John Doe
     * @bodyParam email string optional User's email. Example: john@example.com
     * @bodyParam phone string optional User's phone. Example: +1234567890
     * @bodyParam is_active boolean optional User active status. Example: true
     * @bodyParam roles array optional Array of role names. Example: ["admin", "user"]
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->update($request->only(['name', 'email', 'phone', 'is_active']));

        if ($request->has('roles')) {
            $user->syncRoles($request->input('roles'));
        }

        return response()->json([
            'user' => new UserResource($user->fresh()->load('profile', 'roles')),
            'message' => 'User updated successfully',
        ]);
    }

    /**
     * @group Admin - User Management
     * 
     * Delete user
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last admin user',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}