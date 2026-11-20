<?php

namespace ThuongDo\Buyder\src\Traits;

use ThuongDo\Buyder\src\Constants\UpdatePlatformOrdersConstant;

trait HookTrait
{
    /**
     * Lấy tên method cho hook UpdatePlatformOrders từ messageType Buyder trả về
     *
     * @param string $messageType
     * @return string|null
     */
    private function getUpdatePlatformOrderMethod(string $messageType): ?string
    {
        return UpdatePlatformOrdersConstant::getConstantValue('METHODS_MAPPING', $messageType);
    }
}
