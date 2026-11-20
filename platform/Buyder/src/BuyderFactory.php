<?php

namespace ThuongDo\Buyder\src;

use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;
use ThuongDo\Buyder\src\Services\BaseService;
use ThuongDo\Buyder\src\Traits\BuyderFactoryTrait;

class BuyderFactory implements BaseFactory
{
    use BuyderFactoryTrait;

    /**
     * Biến này quyết định sẽ sử dụng service nào
     * Danh sách các service hiện có được liệt kê trong BuyderServiceConstant
     *
     * @var BaseService
     */
    private static BaseService $service;

    /**
     * Method này quyết định sẽ sử dụng Service nào, mặc định sẽ sử dụng ServiceTaobao
     *
     * @param string $serviceName
     * @return BaseService
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function connection(string $serviceName = BuyderServiceConstant::SYNC_TAOBAO): BaseService
    {
        if (!in_array($serviceName, BuyderServiceConstant::AVAILABLE_SERVICES)) {
            throw new \Exception('Service chưa được hỗ trợ');
        }

        self::$service = app()->make($serviceName);

        return self::$service;
    }

    /**
     * Nếu public static function không tồn tại thì forward call sang public function của Service
     * Mặc định sử dụng ServiceTaobao
     *
     * @param $method
     * @param $parameters
     * @return mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function __callStatic($method, $parameters)
    {
        return static::connection()->$method(...$parameters);
    }

    /**
     * Nếu public function không tồn tại thì forward call sang public function của Service
     * Mặc định sử dụng ServiceTaobao
     *
     * @param $method
     * @param $parameters
     * @return mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __call($method, $parameters)
    {
        return static::connection()->$method(...$parameters);
    }
}
