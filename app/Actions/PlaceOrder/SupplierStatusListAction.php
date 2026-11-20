<?php

namespace App\Actions\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Models\AssignSupportOrder;
use App\Models\Order;
use App\Models\OrderBuyder;
use App\Traits\PlaceOrder\GetPlatformStatusConstantNameTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ThuongDo\Buyder\src\Actions\PlaceOrder\PlaceOrderBaseAction as BaseAction;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class SupplierStatusListAction extends BaseAction
{
    use GetPlatformStatusConstantNameTrait;

    /**
     * Danh sách trạng thái đặt hàng (order_buyders.supplier_status hoặc order_buyder_subs.buyder_status)
     * Nghĩa là danh sách trạng thái đơn đặt hàng to hoặc đơn đặt hàng con
     *
     * @param array|null $params
     * @return array
     */
    public function handle(?array $params): array
    {
        $method = data_get($params, 'method');

        return $this->{$method}();
    }

    /**
     * Danh sách trạng thái đơn to (order_buyders.supplier_status), kèm số lượng order_buyders tương ứng
     * Lấy các order của nhân viên này, từ đó lấy ra các order_buyders tương ứng
     *
     * @return array< array{code: string, name: string, color: string, count: int} >
     */
    private function getSupplierStatusList(): array
    {
        // nguồn đặt hàng ('taobao' hoặc '1688'), mặc định là 'taobao'
        $request = request();
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;

        // Taobao và 1688 có các trạng thái đặt hàng khác nhau, lưu trong các constant khác nhau
        // method này được định nghĩa trong trait GetPlatformStatusConstantNameTrait
        $constantNames = $this->getSupplierStatusListConstantNames($source);
        // $rawData: array< array{code: string, name: string, color: string} >
        $rawData = $this->getRawData($constantNames[0], $constantNames[1]);

        // lấy mọi bản ghi order_buyders của auth user
        $orderBuyders = OrderBuyder::query()
            ->select(['supplier_status'])
            ->whereSource($source)
            ->whereHas('orders', fn ($query) => $query->owned())
            ->get();

        // kết quả trả về
        return $this->getResult($rawData, $orderBuyders, 'supplier_status');
    }

    /**
     * Danh sách trạng thái đơn con (order_buyder_subs.buyder_status), kèm số lượng order_buyder_subs tương ứng
     * Chỉ lâ các đơn đặt hàng tương ứng các đơn mình được nhờ hỗ trợ
     *
     * @return array< array{code: string, name: string, color: string, count: int} >
     */
    private function getSupplierSubStatusList(): array
    {
        // nguồn đặt hàng ('taobao' hoặc '1688'), mặc định là 'taobao'
        $request = request();
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;

        // Taobao và 1688 có các trạng thái đặt hàng khác nhau, lưu trong các constant khác nhau
        // method này được định nghĩa trong trait GetPlatformStatusConstantNameTrait
        $constantNames = $this->getSupplierSubStatusListConstantNames($source);
        // $rawData: array< array{code: string, name: string, color: string} >
        $rawData = $this->getRawData($constantNames[0], $constantNames[1]);

        // lấy các assign_support_orders của nhân viên này, kèm các order_buyder_subs tương ứng
        $assignSupportOrders = AssignSupportOrder::query()
            ->with([
                'orderBuyderSubs' => fn ($query) => $query
                    ->withoutGlobalScope('without_canceled')
                    ->whereSource($source)
            ])->assigned()->placed()->get();
        $orderBuyderSubs = $assignSupportOrders->pluck('orderBuyderSubs')->flatten(1);

        // kết quả trả về
        return $this->getResult(
            $rawData,
            $orderBuyderSubs,
            'buyder_status'
        );
    }

    /**
     * Danh sách trạng thái đặt hàng màn 'Danh sách thanh toán'
     *
     * @return array[]
     */
    private function getPaymentSupplierStatusList(): array
    {
        $request = request();
        $source = $request->get('source') ?? BuyderServiceConstant::SOURCE_TAOBAO;

        $constantNames = $this->getPaymentSupplierStatusListConstantNames($source);
        $rawData = $this->getRawData($constantNames[0], $constantNames[1]);

        $orderBuyders = OrderBuyder::query()
            ->hasOrderBuyderSubs()
            ->whereShownInPaymentListPage()
            ->owned()->get();

        // kết quả trả về
        return $this->getResult(
            $rawData,
            $orderBuyders,
            'supplier_status'
        );
    }

    /**
     * Lấy dữ liệu raw chứa tên và màu sắc của trạng thái
     *
     * @param string $constant
     * @param string $color
     * @return array<array{code: string, name: string, color: string}>
     */
    private function getRawData(string $constant, string $color): array
    {
        $status = BuyderStatusConstant::getFormattedArrayConstant($constant);
        $colors = BuyderStatusConstant::getFormattedArrayConstant($color);

        return Arr::map($status, function ($status) use ($colors) {
            return array_merge($status, [
                'color' => Arr::first(
                    $colors,
                    fn ($color) => data_get($color, 'code') == data_get($status, 'code')
                )['name']
            ]);
        });
    }

    /**
     * Format kết quả trả về
     *
     * @param array $rawData
     * @param Collection $collection
     * @param string $column
     * @return array<array{code: string, name: string, color: string, count: int}>
     */
    private function getResult(array $rawData, Collection $collection, string $column): array
    {
        $values = Arr::map($rawData, function ($data) use ($collection, $column) {
            return array_merge($data, [
                'count' => $collection->where($column, data_get($data, 'code'))->count(),
            ]);
        });

        array_unshift($values, $this->getTotal((int) array_sum(Arr::pluck($values, 'count'))));
        return $values;
    }

    /**
     * Lấy giá trị cho cột 'Tất cả'
     *
     * @param Collection $collection
     * @return array
     */
    private function getTotal(int $total): array
    {
        return [
            'code' => null,
            'name' => 'Tất cả',
            'color' => 'rgba(237, 28, 36, 1)',
            'count' => $total
        ];
    }
}
