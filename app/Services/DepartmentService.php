<?php

namespace App\Services;
use App\Constants\RoleConstant;
use App\Helpers\RandomHelper;
use App\Http\Resources\Department\DepartmentResource;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Class DepartmentService
 * @package App\Services
 */
class DepartmentService extends BaseService
{
    protected string $_resource = DepartmentResource::class;

    public function store(array $data): JsonResponse
    {
        $res = DB::transaction(function () use ($data) {
            $user = \Auth::user();
            $data['organization_id'] = $user->organization_id ?? getOrganization();

            $record = Department::create($data);
            // $role = Role::create(
            //     [
            //         'name' => RandomHelper::roleName($data['name']),
            //         'guard_name' => 'api',
            //         'is_department' => true
            //     ]
            // );
            // $record->assignRole($role);
            // if(isset($data['permissions']) && count($data['permissions']) > 0) {
            //     (new RolePermissionService)->assignPermissionToRole($data['permissions'], $role->id);
            // }
            return $record;
        });
        return resSuccessWithinData($res);
    }


    public function update(string $id, array $data): JsonResponse
    {
        $res = DB::transaction(function () use ($id, $data) {
            $record = $this->_model->newQuery()->findOrFail($id);
            $record->update($data);
            // $role_id = $record->roles[0]->pivot->role_id;
            // $role = Role::query()->where('id', $role_id)->first();
            // RolePermission::query()->where('role_id', $role->id)->delete();
            // (new RolePermissionService)->assignPermissionToRole($data['permissions'], $role->id);
            // (new StaffService())->permissionToggle($id);
            return $record;
        });
        return resSuccessWithinData($res);
    }

    public function destroy(string $id): JsonResponse
    {
        $staff = Staff::query()->where('department_id', $id)->get();
        if(count($staff) > 0) {
            $result = [
                'unit' => 'Phòng ban khi đang có nhân viên trực thuộc thì không được xóa'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            // throw new \Exception(trans('department.department_has_user'));
        }
        $record = Department::query()->findOrFail($id);
        if($record->type != null) {
            return resError('Không được xóa phòng ban mặc định từ hệ thống!');
        }
        $res = DB::transaction(function () use ($record) {
            // if(isset($record->roles[0])) {
            //     $role_id = $record->roles[0]->pivot->role_id;
            //     $role = Role::query()->where('id', $role_id)->first();
            //     RolePermission::query()->where('role_id', $role->id)->delete();
            // }
            $record->delete();
            return $record;
        });
        return resSuccessWithinData($res);
    }

}
