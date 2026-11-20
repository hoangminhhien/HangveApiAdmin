<?php

namespace App\Actions\PlaceOrder;

use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Collection;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;

class GetOrderSubsAction extends BaseAction
{
    /**
     * Danh sách các order_buyder_subs thuộc một order hoặc order_detail
     *
     * @param array|null $params
     * @return Collection
     */
    public function handle(?array $params): Collection
    {
        $method = 'getAll' . ucfirst($params['type']) . 'Subs';

        return $this->{$method}($params['id']);
    }

    /**
     * Danh sách các order_buyder_subs thuộc một order
     *
     * @param string $id
     * @return Collection
     */
    private function getAllOrderSubs(string $id): Collection
    {
        return Order::query()->findOrFail($id)->subOrderBuyders();
    }

    /**
     * Danh sách các order_buyder_subs thuộc một order_detail
     *
     * @param string $id
     * @return Collection
     */
    private function getAllOrderDetailSubs(string $id): Collection
    {
        return OrderDetail::query()->findOrFail($id)->subOrderBuyders()->get();
    }
}
