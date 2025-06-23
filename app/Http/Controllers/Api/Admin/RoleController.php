<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.view')->only(['index', 'show']);
        $this->middleware('permission:roles.create')->only(['store']);
        $this->middleware('permission:roles.edit')->only(['update']);
        $this->middleware('permission:roles.delete')->only(['destroy']);
    }

    /**
     * @group Admin - Roles
     * 
     * List all roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->paginate(15);

        return response()->json($roles);
    }

    /**
     * @group Admin - Roles
     * 
     * Create new role
     * 
     * @bodyParam name string required Role name. Example: editor
     * @bodyParam permissions array optional Array of permission IDs. Example: [1, 2, 3]
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'role' => $role->load('permissions'),
            'message' => 'Role created successfully',
        ], 201);
    }

    /**
     * @group Admin - Roles
     * 
     * Show role details
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role->load('permissions'),
        ]);
    }

    /**
     * @group Admin - Roles
     * 
     * Update role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'role' => $role->fresh()->load('permissions'),
            'message' => 'Role updated successfully',
        ]);
    }

    /**
     * @group Admin - Roles
     * 
     * Delete role
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'admin') {
            return response()->json([
                'message' => 'Cannot delete admin role',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}