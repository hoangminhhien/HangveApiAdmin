<?php


namespace App\Services;


use App\Models\Supplier;
use App\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Supplier\SupplierResource;

class SupplierService extends BaseService
{
    protected string $_resource = SupplierResource::class;

    /**
     * @param string $organizationId
     * @return Builder|Model|object|null
     */
    public function getSupplierRandomByOrganization(string $organizationId)
    {
        return Supplier::query()->withoutGlobalScope(OrganizationScope::class)->where(
            'organization_id',
            $organizationId
        )->inRandomOrder()->first();
    }

    public function updateTotalOrderAmountOrder($id)
    {
        $orderSupplierModel = \App::make('App\Models\OrderSupplier');
        $one = $orderSupplierModel->where('order_id', $id)->first();

        // Lấy ra toàn bộ đơn của ncc này
        $mores = $orderSupplierModel->where('supplier_id', $one->supplier_id)->get();
        $order_ids = \Arr::pluck($mores, 'order_id');

        // Lấy ra đơn hàng nó đã có trạng thái đặt cọc
        $orderStatusTime = \App::make('App\Models\OrderStatusTime');
        $orders = $orderStatusTime->whereIn('order_id', $order_ids)
                                    ->whereNotNull('status_2')
                                    ->get();
        $order_ids_status_2 = \Arr::pluck($orders, 'order_id');

        // Đếm tiền
        $dones = $orderSupplierModel->whereIn('order_id', $order_ids_status_2)->get();

        $total_money = 0;
        if (count($dones)) {
            foreach ($dones as $key => $item) {
                $total_money += $item->order_cost;
            }
            // Cập nhật vào Supplider
            $supplierModel = \App::make('App\Models\Supplier');
            $supplier = $supplierModel->where('id', $one->supplier_id)->first();

            $supplier->order_amount = $total_money;
            $supplier->save();
        }
    }

    public function getListSup($request)
    {
        $params = $request->only('customer_id', 'page', 'per_page');

        $sup = Supplier::query()
            ->where('customer_id', $params['customer_id'])
            ->orderBy('order_amount', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->paginate($params['per_page']);
        return resSuccessWithinData(new $this->_paginateResource($sup, $this->_resource));
    }

    public function getSupplierByCustomer(int $orderId)
    {
        return Supplier::query()
            ->select( 'suppliers.*')
            ->join('order_details', 'suppliers.id', '=', 'order_details.supplier_id')
            ->where('order_id', $orderId)
            ->distinct('suppliers.id')
            ->get();
    }
}
