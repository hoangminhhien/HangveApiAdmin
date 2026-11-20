<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rap2hpoutre\FastExcel\FastExcel;
use OpenSpout\Common\Entity\Style\Style;
use Throwable;
use Illuminate\Support\Carbon;
use App\Constants\OrderConstant;
use App\Services\AccountingService;
use App\Constants\CustomerConstant;

class ExportOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $params;
    protected $auth;
    protected $pathFile;
    public function __construct($params, $auth, $pathFile)
    {
        $this->params = $params;
        $this->auth = $auth;
        $this->pathFile = $pathFile;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        \Log::info("-------Start queue export danh sách đơn hàng--------");
        $order = $this->myGenerator();
        if(!file_exists(public_path(getPathFileExport('order')))){
            mkdir(public_path(getPathFileExport('order')), 0777, true);
        }
        $header_style = (new Style())->setFontBold()->setBackgroundColor("EDEDED");
        (new FastExcel($order))
        ->headerStyle($header_style)
        ->export(public_path($this->pathFile), function($order){
            $totalAmount = ($order->thuongdo_id && $order->has_edit == 0) ? $order->thuongdo_tong_tien_don_hang : $this->getTotalAmount($order);
            $totalAmount = round($totalAmount);
            $depositCost = $order->thuongdo_id ? $order->thuongdo_tong_tien_tam_ung : $order->deposit_cost;
            if($order->delivery_pay_id > 0) {
                $depositCost += $order->delivery_pay_debt_cost;
            }
            // $orderCost = 0;
            // $orderFee = 0;
            // if($order->official == OrderConstant::OFFICIAL && $order->official_is_partner == 0) {
            //     $orderCost = $order->official_order_cost;
            //     $orderFee = $order->official_service_fee;
            // }else {
            //     $orderCost = $order->order_cost;
            //     $orderFee = $order->order_fee;
            // }
            $orderCost = $order->order_cost;
            $orderFee = $order->order_fee;
            $data = [
                'Mã đơn hàng' => $order->code,
                'Ngày tạo đơn' => Carbon::parse($order->created_at)->format('d-m-Y H:i:s'),
                'Ngày báo giá' => $order->date_quotation ? Carbon::parse($order->date_quotation)->format('d-m-Y H:i:s') : '',
                'Ngày đặt cọc' => $order->date_purchased ? Carbon::parse($order->date_purchased)->format('d-m-Y H:i:s') : '',
                'Ngày đặt hàng' => $order->date_ordered ? Carbon::parse($order->date_ordered)->format('d-m-Y H:i:s') : '',
                'Ngày hoàn thành' => $order->date_done ? Carbon::parse($order->date_done)->format('d-m-Y H:i:s') : '',
                'Tên KH' =>  data_get($order->customer, 'name'),
                'Mã KH' =>  data_get($order->customer, 'code'),
                'SDT' => data_get($order->customer, 'phone_number'),
                'Cấp độ' => data_get($order->customer, 'level') ? 'Cấp '. data_get($order->customer, 'level') : "",
                'Loại hình' => data_get($order->customer, 'business_type') ? CustomerConstant::CUSTOMER_BUSINESS_TYPE[data_get($order->customer, 'business_type')] : "",
                'NV báo giá' => data_get($order->staffQuotation, 'name'),
                'NV đặt hàng' => data_get($order->staffOrder, 'name'),
                'NV kinh doanh' => data_get($order->staffGroup, 'name'),
                'Tình trạng' => array_key_exists($order->status, OrderConstant::STATUSES) ? OrderConstant::STATUSES[$order->status] : '',
                'Tổng tiền' => roundMoney($totalAmount),
                'Tổng tiền hàng' => (int) $orderCost,
                'Phí dịch vụ' => (int) $orderFee,
                'Tổng giá trị hàng hóa' => (int) $order->official_order_cost,
                'Phí dịch vụ CN' => (int) $order->official_service_fee,
                'Tạm ứng' => $depositCost,
                'Còn lại' => roundMoney($totalAmount) - $depositCost,
            ];
            if($this->hasPermissionName('order.show_negotiate_excel')) {
                $data['Phí đàm phán'] = $this->getDiff($order);
            }
            return $data;
        });
        $this->senNoti(11, 11005, data_get($this->auth, 'id'), $this->pathFile);
        \Log::info("-------End queue export danh sách đơn hàng--------");
    }

    public function myGenerator()
    {
        $data = \App::make('App\Services\OrderService')->getDataExportList($this->params, $this->auth);
        foreach ($data->lazy() as $key => $order) {
            yield $order;
        }
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        $this->senNoti(11, 11006, data_get($this->auth, 'id'));
        \Log::info("-------End queue export danh sách đơn hàng--------");
    }

    private function senNoti($group, $name, $userId, $pathFile = null)
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $data_info = [
            'path_file'         => $pathFile
        ];
        $data = [
            'group'           => $group,
            'name'            => $name,
            'data'            => json_encode($data_info),
            'time_create'     => time(),
            'status'          => 1
        ];
        $notificationService->storeNoti($userId, $data);
    }

    private function getTotalAmount($order)
    {
        $orderStatusCalFee = [OrderConstant::KEY_STATUS_WAITING_QUOTE, OrderConstant::KEY_STATUS_WAITING_DEPOSIT];
        if(!$order){
            return 0;
        }
        $orderSuppliers = data_get($order, 'orderSuppliers', []);
        $totalDiscountCostPacks = !empty($order->packages) ? $order->packages->sum('discount_cost') : 0;
        $totalAmountRound = 0;
        $totalShipPackNotRound = 0;
        $count = $orderSuppliers->count();
        $customerId = $order->customer_id;
        $orderPercent = $order->order_cost ? ($order->order_fee / $order->order_cost) * 100 : 0;
        foreach ($orderSuppliers as $key => $sup) {
            $orderFee  = $sup->order_fee;
            $orderCost = $sup->order_cost;
            $discountCost = $sup->discount_cost;
            if (in_array($order->status, $orderStatusCalFee)) {
                $orderFee = $count > 1 ? (new AccountingService())
                    ->getOrderFee($orderCost, $customerId, $orderPercent, 'isMultipleSupplier')
                    : (new AccountingService())
                        ->getOrderFee($orderCost, $customerId);
                $discountCost = (new AccountingService())->getDiscountCost($orderFee, $customerId);
            }
            $temp_sub = 0;
            $temp_sub += $sup->china_shipping_cost * $order->exchange_rate;
            $temp_sub += $orderFee;
            $temp_sub += $orderCost;
            $temp_sub += $sup->inspection_cost;
            $temp_sub -= $discountCost;
            $temp_sub = roundMoney($temp_sub);
            $totalAmountRound += $temp_sub;
            $totalShipPackNotRound = $sup->woodworking_cost + $sup->international_shipping_cost - $totalDiscountCostPacks;
        }
        $totalAmount = $totalAmountRound + $totalShipPackNotRound;
        return $totalAmount;
    }

    private function getDiff($order)
    {
        /**
         * Công thức
         * Tiền hàng đã trả + Phí TQ đã trả - (Tiền hàng thực tế + phí ship TQ thực tế)
        */
        $orderCostReal = (float)$order->order_cost_real; // Tiền hàng thực tế
        $chinaShippingCostReal = (float)$order->china_shipping_cost_real * $order->exchange_rate; // phí ship TQ thực tế
        $orderCostOld = (float)$order->order_cost; // Tiền hàng đã trả
        $chinaShippingCostOld = (float)$order->china_shipping_cost * $order->exchange_rate; //  Phí TQ đã trả
        $bonus = (float)$order->bonus * $order->exchange_rate;

        return $orderCostOld + $chinaShippingCostOld  - ($orderCostReal + $chinaShippingCostReal) + $bonus;
    }

    private function hasPermissionName(string $permission): bool
    {
        $permissions = [];
        $roles = $this->auth ? $this->auth->roles : getCurrentUser()->roles;
        if($this->auth) {
            $permissions = $this->auth->getAllPermissions()->pluck('name')->toArray();
        }
        return in_array($permission, $permissions);
    }
}
