<?php

namespace ThuongDo\Buyder\src\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

trait BuyderFactoryTrait
{
    /**
     * Lấy danh sách các nguồn đặt hàng, kèm hình ảnh
     *
     * @return JsonResponse
     */
    public static function getListSource(): JsonResponse
    {
        $rawSources = BuyderServiceConstant::getFormattedArrayConstant('SERVICE_IMAGES');

        $sources = Arr::map($rawSources, fn ($source) => ([
            'code' => data_get($source, 'code'),
            'image' => data_get($source, 'name')
        ]));

        return resSuccessWithinData($sources);
    }
}
