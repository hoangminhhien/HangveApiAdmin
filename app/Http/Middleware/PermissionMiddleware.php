<?php

namespace App\Http\Middleware;

use Closure;

class PermissionMiddleware
{
    /**
     * @param $request
     * @param Closure $next
     * @param $permission
     * @return mixed
     */
    public function handle($request, Closure $next, $permission)
    {
        // Xử lý mới
        $permissions = is_array($permission) ? $permission : explode('|', $permission);

        $allDontHavePer = true; // Toàn bộ các quyền là đều không có

        $userOnline = auth()->user();
        $permissionDBs = $userOnline->getAllPermissions();
        $perOfRole = \Arr::pluck($permissionDBs, 'name');

        foreach ($permissions as $key => $permission) {
            if (in_array($permission, $perOfRole)) {
                $allDontHavePer = false;
            }
        }
        if ($allDontHavePer) {
            $result = ['err' => 'Bạn không có quyền thực hiện thao tác này!'];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        return $next($request);

        // Logic cũ
        $permissions = is_array($permission) ? $permission : explode('|', $permission);
        $userOnline = auth()->user();

        return $userOnline->hasAnyDirectPermission($permissions)
            ? $next($request)
            : resErrorWithinData(
                ['err' => 'Bạn không có quyền thực hiện thao tác này!'],
                'Có lỗi xảy ra',
                422
            );
    }
}
