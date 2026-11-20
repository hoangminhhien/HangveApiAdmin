<?php

namespace ThuongDo\Buyder\src\Traits;

use Illuminate\Http\JsonResponse;

trait AuthServiceTrait
{
    /**
     * Kiểm tra xem auth user đã có id đặt hàng tương ứng với hành động đang làm chưa
     * Ví dụ
     *   + Các tác vụ Taobao thì kiểm tra đã có 'users.id_taobao' chưa
     *   + Các tác vụ 1688 thì kiểm tra đã có 'users.id_1688' chưa
     *
     * @param string $className
     * @return JsonResponse|string
     */
    public function checkIfUserHavePlatformId(string $className)
    {
        // $className có dạng 'ThuongDo\src\Services\ServiceTaobao.php'
        $source = str_replace('service', '', strtolower(class_basename($className)));
        // platformId là id_taobao hoặc id_1688 trong bảng 'users'
        $platformId = data_get(auth()->user(), 'id_' . $source);
        /*if ($source == 'taobao') {
            $platformId = '2100008382470';
        } else {
            $platformId = '2218998220969';
        }*/
        return is_null($platformId)
            ? resError('Bạn không thể thực hiện hành động này do chưa có ID ' . ucfirst($source), 403)
            : $platformId;
    }
}
