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
use App\Constants\PackageConstant;
use App\Models\Order;
use App\Models\Consignment;

class ExportPackageJob implements ShouldQueue
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
        \Log::info("-------Start queue export danh sách kiện hàng--------");
        $packages = $this->myGenerator();
        if(!file_exists(public_path(getPathFileExport('package')))){
            mkdir(public_path(getPathFileExport('package')), 0777, true);
        }
        $header_style = (new Style())->setFontBold()->setBackgroundColor("EDEDED");
        (new FastExcel($packages))
        ->headerStyle($header_style)
        ->export(public_path($this->pathFile), function($package){
            $type = 'Chờ xác nhận';
            if($package->order_type == Order::class) {
                $type = 'Hàng order';
            }
            if($package->order_type == Consignment::class) {
                $type = 'Hàng kí gửi';
            }
            return [
                'Ngày tạo' => $package->created_at ? Carbon::parse($package->created_at)->format('d-m-Y H:i:s') : '',
                'Ngày nhận hàng' => $package->date_done ? Carbon::parse($package->date_done)->format('d-m-Y H:i:s') : '',
                'Ngày chuyển trạng thái' => $package->statusTimeLast ? Carbon::parse($package->statusTimeLast->created_at)->format('d-m-Y H:i:s') : '',
                'Mã vận đơn' => $package->code_warehouse,
                'Mã vận đơn gốc' => $package->bill_code,
                'Mã đặt hàng' => $package->code_po,
                'Mã đơn hàng' => $package->order_code,
                'Tên KH' =>  data_get($package->customer, 'name'),
                'Mã KH' =>  data_get($package->customer, 'code'),
                'SDT' => data_get($package->customer, 'phone_number'),
                'Kho' => data_get($package->warehouse, 'code'),
                'Loại hàng' => $type,
                'Danh mục' => data_get($package->categoryRelation, 'name'),
                'Số kiện' => $package->package_number,
                'Trọng lượng/Thể tích' => $package->type_quantitative == 'weight' ? $package->weight : $package->volume,
                'Đơn vị' => $package->type_quantitative == 'weight' ? 'kg' : 'm3',
                'NV quản lý' =>  data_get($package->staffGroup, 'name'),
                'NV đặt hàng' => data_get($package->staffOrder, 'name'),
                'Tình trạng' => array_key_exists($package->status, PackageConstant::STATUSES) ? PackageConstant::STATUSES[$package->status] : '',
                'Phí nội địa' => $package->china_shipping_cost,
                'Phí vận chuyển QT' => $package->international_shipping_cost,
                'Chiết khấu' => $package->discount_cost,
                'Tổng phí' => (float) $package->total_amount
            ];
        });
        $this->senNoti(11, 11007, data_get($this->auth, 'id'), $this->pathFile);
        \Log::info("-------End queue export danh sách kiện hàng--------");
    }

    public function myGenerator()
    {
        $data = \App::make('App\Services\OrderPackageService')->getDataExportList($this->params, $this->auth);
        foreach ($data->lazy() as $key => $package) {
            yield $package;
        }
    }

    public function failed(Throwable $exception): void
    {
        \Log::error($exception);
        $this->senNoti(11, 11008, data_get($this->auth, 'id'));
        \Log::info("-------End queue export danh sách kiện hàng--------");
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
