<?php

namespace ThuongDo\Buyder\src\Actions\PlaceOrder;

use ThuongDo\Buyder\src\Actions\BaseAction;
use Illuminate\Support\Arr;

class PlaceOrderBaseAction extends BaseAction
{
    /**
     * @inheritDoc
     */
    public function handle(?array $params)
    {
    }

    protected function getProductSku(?array $skuList, $productClassification): ?object
    {
        if (!$skuList) {
            return null;
        }
        if (!$productClassification) {
            return null;
        }

        return Arr::find($skuList, function ($sku) use ($productClassification) {
            $props = Arr::pluck($sku->properties, 'valueName');

            // tạo mảng chưa mọi tổ hợp có thể có của các thuộc tính, nhằm so sánh với $productClassification
            $combinations = [];
            foreach ($props as $element1) {
                foreach ($props as $element2) {
                    if ($element1 != $element2) {
                        $combination = implode(';', [$element1, $element2]);
                        $combinations[] = $combination;
                    }
                }
            }

            if (in_array($productClassification, $combinations)) {
                return $sku;
            }
        });
    }
}
