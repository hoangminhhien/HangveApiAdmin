<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnsureUserHavePlatformId
{
    /**
     * Middleware này làm nhiệm vụ kiểm tra user phải có ID đặt hàng Taobao hoặc 1688
     * users.id_taobao, users.id_1688
     *
     * @param Request $request
     * @param Closure $next
     * @return JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $idTaobao = data_get($user, 'id_taobao');
        $id1688 = data_get($user, 'id_1688');
        /*$idTaobao = "2100008382470";
        $id1688 = '2218998220969';*/
        // thêm các field này vào trong request
        $idTaobao && $request->offsetSet('id_taobao', $idTaobao);
        $id1688 && $request->offsetSet('id_1688', $id1688);

        return $idTaobao || $id1688
            ? $next($request)
            : resError('Bạn không thể thực hiện hành động này do chưa có ID đặt hàng Taobao/1688', 403);
    }
}
