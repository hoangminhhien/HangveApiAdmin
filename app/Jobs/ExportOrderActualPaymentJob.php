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

class ExportOrderActualPaymentJob implements ShouldQueue
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
        \Log::info("-------Start queue export thanh toán thức tế NCC--------");
        $orders = $this->myGenerator();
        if(!file_exists(public_path(getPathFileExport('order')))){
            mkdir(public_path(getPathFileExport('order')), 0777, true);
        }
        $header_style = (new Style())->setFontBold()->setBackgroundColor("EDEDED");
        (new FastExcel($orders))
        ->headerStyle($header_style)
        ->export(public_path($this->pathFile), function($order){
            $codePo = !$order->packagesOrder->isEmpty() ? $order->packagesOrder[0] : '';
            if($codePo) {
                $codePo = $codePo->code_po;
            }
            $orderCostReal = round(($order->order_cost_real / $order->exchange_rate), 2);
            $chinaShippingCostReal = (float)$order->china_shipping_cost_real;
            $orderCost = round(($order->order_cost / $order->exchange_rate), 2);
            $chinaShippingCost = (float)$order->china_shipping_cost;
            $bonus = (float)$order->bonus;
            return [
                'Ngày đặt hàng' => $order->created_at ? Carbon::parse($order->created_at)->format('d-m-Y H:i:s') : '',
                'Mã đặt hàng' =>  $codePo,
                'Mã đơn hàng' => $order->code,
                'Tiền hàng khách trả' => $orderCost,
                'Tiền hàng TT' => $orderCostReal,
                'Phí ship khác trả' => $chinaShippingCost,
                'Phí ship TT' =>  $chinaShippingCostReal,
                'Tỉ giá' => $order->exchange_rate,
                'Chênh lệch(CNY)' => $orderCost + $chinaShippingCost - ($orderCostReal + $chinaShippingCostReal) + $bonus,
                'Chênh lệch(VND)' => ($orderCost + $chinaShippingCost - ($orderCostReal + $chinaShippingCostReal) + $bonus) * $order->exchange_rate,
                'Bonus' => $bonus,
                'Tổng tiền KH trả' => $orderCost + $chinaShippingCost,
                'Tổng tiền thanh toán TT' => $orderCostReal + $chinaShippingCostReal,
                'NV đặt hàng' => data_get($order->staffOrder, 'name'),
            ];
        });
        $this->senNoti(11, 11009, data_get($this->auth, 'id'), $this->pathFile);
        \Log::info("-------End queue export thanh toán thức tế NCC--------");
    }

    public function myGenerator()
    {
        $data = \App::make('App\Services\OrderService')->getDataExportActualPayment($this->params);
        foreach ($data->lazy() as $key => $order) {
            yield $order;
        }
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        $this->senNoti(11, 11010, data_get($this->auth, 'id'));
        \Log::info("-------End queue export thanh toán thức tế NCC--------");
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
}
