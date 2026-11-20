<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Customer;
use App\Models\User;
use Rap2hpoutre\FastExcel\FastExcel;
use OpenSpout\Common\Entity\Style\Style;
use Throwable;
use App\Constants\CustomerConstant;
use Illuminate\Support\Carbon;
use DB;


class ExportCustomerV2 implements ShouldQueue
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
        \Log::info("-------Start queue export danh sách khách hàng--------");
        $customer = $this->myGenerator();
        if(!file_exists(public_path(getPathFileExport('customer')))){
            mkdir(public_path(getPathFileExport('customer')), 0777, true);
        }
        $header_style = (new Style())->setFontBold()->setBackgroundColor("EDEDED");
        (new FastExcel($customer))
        ->headerStyle($header_style)
        ->export(public_path($this->pathFile), function($customer){
            $offer = $customer->offer;
            $deliveryDefault = $customer->deliveryDefault;
            $province = data_get($customer, 'province');
            $district = data_get($deliveryDefault, 'district');
            $ward = data_get($deliveryDefault, 'ward');
            $staffGroup = $customer->staffGroup;
            return [
                'Mã khách hàng' => $customer->code,
                'Tên' => $customer->name,
                'Số điện thoại' => $customer->phone_number,
                'Mức vip' => 'Cấp '. $customer->level,
                'Loại hình' => $customer->business_type ? CustomerConstant::CUSTOMER_BUSINESS_TYPE[$customer->business_type] : '',
                'Emai' => $customer->email,
                'Tỉnh TP' => $province ? $province->name : '',
                'Quận huyện' => $district ? $district->name : '',
                'Phường/xã' => $ward ? $ward->name : '',
                'Địa chỉ' => $deliveryDefault ? $deliveryDefault->address : '',
                'Ngày đăng ký' => Carbon::parse($customer->created_at)->format('d-m-Y H:i:s'),
                'Tổng đơn hàng' => $customer->total_orders,
                'Tổng tiền nạp ví' => $customer->report ? number_format($customer->report->deposited_amount,0,".",".") . ' đ'  : '',
                'Số dư tài khoản' => $customer->report ? number_format($customer->report->balance_amount,0,".",".") . ' đ'  : '',
                'Tình trạng' => $customer->status == CustomerConstant::STATUS_ACTIVE ? 'Đang hoạt động' : 'Đã khóa',
                'NV quản lý' => $staffGroup ? $staffGroup->name : '',
                'HN-Kg' => $offer ? $offer->trans_weight_hn_fee : '',
                'M3' => $offer ? $offer->trans_volumn_hn_fee : '',
                'HP-Kg' => $offer ? $offer->trans_weight_hp_fee : '',
                'M3' => $offer ? $offer->trans_volumn_hp_fee : '',
                'HCM-Kg' => $offer ? $offer->trans_weight_hcm_fee : '',
                'M3' => $offer ? $offer->trans_volumn_hcm_fee : '',
                'Ngày đặt hàng gần nhất' => $customer->last_order_at ? Carbon::parse($customer->last_order_at)->format('d-m-Y H:i:s') : ''
            ];
        });
        $this->senNoti(11, 11001, data_get($this->auth, 'id'), $this->pathFile);
        \Log::info("-------End queue export danh sách khách hàng--------");
    }
    public function myGenerator()
    {
        $data = \App::make('App\Services\CustomerService')->getDataExportList($this->params, $this->auth);
        foreach ($data->lazy() as $key => $customer) {
            yield $customer;
        }
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        $this->senNoti(11, 11002, data_get($this->auth, 'id'));
        \Log::info("-------End queue export danh sách khách hàng--------");
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
