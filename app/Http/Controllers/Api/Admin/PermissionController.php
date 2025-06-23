<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:permissions.view')->only(['index', 'show']);
        $this->middleware('permission:permissions.create')->only(['store']);
        $this->middleware('permission:permissions.edit')->only(['update']);
        $this->middleware('permission:permissions.delete')->only(['destroy']);
    }

    /**
     * @group Admin - Permissions
     * 
     * List all permissions
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::paginate(15);

        return response()->json($permissions);
    }

    /**
     * @group Admin - Permissions
     * 
     * Create new permission
     * 
     * @bodyParam name string required Permission name. Example: posts.create
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'permission' => $permission,
            'message' => 'Permission created successfully',
        ], 201);
    }

    /**
     * @group Admin - Permissions
     * 
     * Show permission details
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'permission' => $permission,
        ]);
    }

    /**
     * @group Admin - Permissions
     * 
     * Update permission
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update(['name' => $request->name]);

        return response()->json([
            'permission' => $permission,
            'message' => 'Permission updated successfully',
        ]);
    }

    /**
     * @group Admin - Permissions
     * 
     * Delete permission
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }
}