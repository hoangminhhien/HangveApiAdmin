<?php

namespace App\Jobs\OneWarehouse;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;
use App\Constants\PackageConstant;

use App\Services\ActivityService;
use App\Services\OrderPackageService;
use App\Constants\ActivityConstant;

// Lưu trạng thái kiện cập nhật từ bên kho về
class SyncStatusPack implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $item;
    protected $dongdong_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($item, $dongdong_id)
    {
        $this->item = $item;
        $this->dongdong_id = $dongdong_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $item = $this->item;
        $dongdong_id = $this->dongdong_id;

        // Gán người thao tác
        $_POST['dongdong_id']  = $dongdong_id;

        $this->updateStatusPackAction($item);
    }

    public function updateStatusPackAction($item)
    {
        // \Log::info('updateStatusPackAction queue: ' . $item['pack_partner_id']);
        $packRepo = \App::make('App\Models\OrderPackage');
        try {
            \DB::beginTransaction();
            $packDB = $packRepo->where('id', $item['pack_partner_id'])->first();
            if ($packDB) {
                $status_new = $this->convertStatusOneToStatusHV($item['one_warehouse_status']);

                if (!is_null($status_new)) {
                    if ($packDB->status != $status_new) {
                        // \Log::info('zô zô in queue .: ' . $item['pack_partner_id']);
                        if ($packDB->status != PackageConstant::STATUS_RECEIVED_GOODS) {

                            // Lấy trạng thái cũ
                            $old = $packDB->status;

                            // Lưu lại
                            $packDB->status = $status_new;
                            try {
                                $packDB->skipObserver = true;
                                $packDB->save();
                            } finally {
                                $packDB->skipObserver = false;
                            }

                            // Chỉ cập nhật những cái cần thiết
                            (new ActivityService())->setLog($packDB, $packDB->status, ActivityConstant::PACKAGE_STATUS);
                            (new OrderPackageService())->reportOrderVN($packDB, $old);
                            (new OrderPackageService())->reportStatusTime($packDB->id, $packDB->status);
                        } else {
                            \Log::info('zô zô in queue chời không dc update: ' . $item['pack_partner_id']);
                        }
                    } else {
                        // \Log::info('Không cập nhật in queue.');
                    }
                    // Bổ sung cập nhật trạng thái cho các kiện liên quan
                    // Copy logic như phía trên là được
                    $arr_status = [
                        PackageConstant::STATUS_RECEIVED_GOODS,
                        PackageConstant::STATUS_CANCEL,
                    ];
                    $packDBs = $packRepo->where('bill_code', $packDB->bill_code)
                                        ->where('order_type', $packDB->order_type)
                                        ->whereNotIn('status', $arr_status)
                                        ->where('id', '<>', $packDB->id)
                                        ->get();
                    if (count($packDBs) > 0) {
                        foreach ($packDBs as $key => $packDB) {
                            $this->changeStatusPackSub($packDB, $status_new);
                        }
                    }
                }
            }
            \DB::commit();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function changeStatusPackSub($packDB, $status_new)
    {
        if ($packDB->status != $status_new) {
            // Lấy trạng thái cũ
            $old = $packDB->status;

            // Lưu lại
            $packDB->status = $status_new;
            try {
                $packDB->skipObserver = true;
                $packDB->save();
            } finally {
                $packDB->skipObserver = false;
            }

            // Chỉ cập nhật những cái cần thiết
            (new ActivityService())->setLog($packDB, $packDB->status, ActivityConstant::PACKAGE_STATUS);
            (new OrderPackageService())->reportOrderVN($packDB, $old);
            (new OrderPackageService())->reportStatusTime($packDB->id, $packDB->status);

        }
    }

    public function convertStatusOneToStatusHV($status_one)
    {
        // Chốt
        // Kho - HV

        // Đang về kho TQ - Đang về kho TQ
        // Đang kho Quảng Châu - Đang ở kho QC
        // Đang kho Bằng Tường - Đến kho Bằng Tường

        // Đang vận chuyển - Đang về VN
        // Đang kho Trung chuyển - Đang kiểm hàng
        // Đã chuyển hàng - Đang chuyển hàng

        // Đến kho VN -> Quét bước cuối cùng VN

        $arr_convert_status = [
            PackageConstant::STATUS_ONE_COMING_TO_WAREHOUSE_TQ            => PackageConstant::STATUS_ON_THE_WAY_CHINA,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_QC                   => PackageConstant::STATUS_IN_GUANGZHOU,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_BT                   => PackageConstant::STATUS_IN_PINGXIANG,
            PackageConstant::STATUS_ONE_IN_WAREHOUSE_DH                   => PackageConstant::STATUS_IN_CHINA,

            PackageConstant::STATUS_ONE_PROCESSING_TRANSPORT              => PackageConstant::STATUS_ON_THE_WAY_VN,
            PackageConstant::STATUS_ONE_COMING_TO_WAREHOUSE_TRANSSHIPMENT => PackageConstant::STATUS_CHECKING_GOODS,
            PackageConstant::STATUS_ONE_DONE_TRANSPORT                    => PackageConstant::STATUS_LOADING_GOODS,

            PackageConstant::STATUS_ONE_IN_WAREHOUSE_VN                   => PackageConstant::STATUS_WAREHOUSE_VN,

            // 2 trạng thái phụ danh riêng cho bên HCM
            PackageConstant::STATUS_ONE_COMING_TO_HCM                     => PackageConstant::STATUS_ON_THE_WAY_HCM,
            PackageConstant::STATUS_ONE_CHECKING_IN_HCM                   => PackageConstant::STATUS_CHECKING_GOODS_HCM,
        ];

        if (array_key_exists($status_one, $arr_convert_status)) {
            return $arr_convert_status[$status_one];
        }
        return null;
    }
}
