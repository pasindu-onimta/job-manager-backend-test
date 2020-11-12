<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\User;

class AuthorizationController extends Controller
{
    public function storeRoles(Request $request)
    {
        $validator  = Validator::make(
            $request->all(),
            [ 
                'roles' => 'required|unique:roles,name'
            ],
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->roles as $key => $role) {
            $permission = Role::create(['name' => $role]);
        }
        return response()->json(['message' => 'Success'], 201);
    }

    public function storePermissions(Request $request)
    {
        $validator  = Validator::make(
            $request->all(),
            [ 
                'permissions' => 'required|unique:permissions,name'
            ],
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->permissions as $key => $permission) {
            $permission = Permission::create(['name' => $permission]);
        }
        return response()->json(['message' => 'Success'], 201);  
    }

    public function givePermissionToARole(Request $request, $role_id)
    {
        $role = Role::findOrFail($role_id);
        foreach ($request->permission_ids as $key => $permission_id) {
            $permission = Permission::findOrFail($permission_id);
            $role->givePermissionTo($permission);
        }
        return response()->json(['message' => 'Success'], 201);  
    }

    public function assignARoleToAUser(Request $request, $user_id)
    {
        $user = User::findOrFail($user_id);
        foreach ($request->role_ids as $key => $role_id) {
            $role = Role::findOrFail($role_id);
            $user->assignRole($role);
        }
        return response()->json(['message' => 'Success'], 201);  
    }

    public function roleNames($user_id)
    {
        $roles = User::findOrFail($user_id)->getRoleNames();
        return $roles;
    }
}
