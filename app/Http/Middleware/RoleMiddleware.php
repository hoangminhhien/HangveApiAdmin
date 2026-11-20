<?php
/**
 * Created by PhpStorm.
 * @author Julyboy <cntt0401.luuvietduc@gmail.com>
 * Date: 4/5/2020
 */

namespace App\Http\Middleware;

use Maklad\Permission\Exceptions\UnauthorizedRole;
use Maklad\Permission\Exceptions\UserNotLoggedIn;
use Maklad\Permission\Helpers;
use Closure;

class RoleMiddleware
{
    /**
     * @param $request
     * @param Closure $next
     * @param $role
     * @return mixed
     * @author Julyboy <cntt0401.luuvietduc@gmail.com>
     * @copyright (c) 4/5/2020
     */
    public function handle($request, Closure $next, $role)
    {
        $roles = \is_array($role) ? $role : \explode('|', $role);

        if (! app('auth')->user()->hasAnyRole($roles)) {
            $result = [
                'err' => 'Bạn không quyền truy cập role này'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        return $next($request);
    }
}
