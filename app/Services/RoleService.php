<?php

namespace App\Services;
use App\Helpers\RandomHelper;
use App\Http\Resources\Role\RoleResource;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Http\JsonResponse;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Class RoleService
 * @package App\Services
 */
class RoleService extends BaseService
{
    protected string $_resource = RoleResource::class;

    /**
     * @param array $data
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(array $data): JsonResponse
    {
        $response = DB::transaction(function () use ($data) {
            $userOnline = \Auth::user();
            $data['guard_name'] = 'api';
            $data['name'] = RandomHelper::roleName($data['display_name']);
            $data['name'] = \Str::slug($data['display_name']) . '.' . $userOnline->organization_id;
            $data['organization_id'] = $userOnline->organization_id;
            $role = Role::create($data);
            // give permission to role
            // (new RolePermissionService)->assignPermissionToRole($data['permissions'], $role->id);
            return $role;
        });
        return resSuccessWithinData($response);
    }

    /**
     * @return JsonResponse
     */
    public function update(string $id, array $data): JsonResponse
    {
        $response = DB::transaction(function () use ($id, $data) {
            $role = Role::findOrFail($id);
            RolePermission::where('role_id', $role->id)->delete();
            // give permission to role
            (new RolePermissionService)->assignPermissionToRole($data['permissions'], $role->id);
            return $role;
        });
        return resSuccessWithinData($response);
    }
}
