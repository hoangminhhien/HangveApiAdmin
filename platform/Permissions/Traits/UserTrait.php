<?php

namespace ThuongDo\Permissions\Traits;

trait UserTrait
{
    /**
     * Kiểm tra xem auth user có vai trò (role) nào đó không
     *
     * @param string $role
     * @return bool
     */
    public function hasRoleName(string $role): bool
    {
        $roles = auth()->user()->roles()->pluck('name');

        return $roles->some(fn ($roleName) => str_contains($roleName, $role));
    }

    /**
     * Kiểm tra auth user có quyền (permission) nào đó không
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermissionName(string $permission, $userLogin = null): bool
    {
        $permissions = [];
        $userLogin = $userLogin ?? getCurrentUser();
        if($userLogin) {
            $permissions = $userLogin->getAllPermissions()->pluck('name')->toArray();
        }
        return in_array($permission, $permissions);
    }

    /**
     *  Kiểm tra auth user có thuộc phòng ban nào đó không
     *
     * @param string $permission
     * @return bool
     */
    public function hasDepartmentName(string $type): bool
    {
        $department = auth()->user()->department;
        return $department && str_contains($department->type, $type);
    }
}
