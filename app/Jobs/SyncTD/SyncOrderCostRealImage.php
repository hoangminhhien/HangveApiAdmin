<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

use App\Helpers\AccountingHelper;
use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

use App\Constants\DeliveryConstant;
use App\Models\OrderPackage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Delivery;

// Đồng bộ hoàn tiền
use App\Constants\RefundConstant;

// Đồng bộ ảnh
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class SyncOrderCostRealImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $refundDB;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($refundDB)
    {
        $this->refundDB = $refundDB;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $refundDB = $this->refundDB;

        \Log::info('SyncOrderCostRealImage V16 Update - 30092024: - v2: ' . $refundDB->id);

        $files = json_decode($refundDB->files, true);
        $thuongdo_files = [];
        $file_first = null;

        foreach ($files as $key_1 => $file) {
            $url = $file['url'];

            $date = date('Ymd');
            $path = public_path() . '/oss';
            $min = 10000000;
            $max = 99999999;
            $fileName = rand($min, $max) . "_" . $file['filename'];

            $link  = $path . '/' . $fileName;
            $pathOSS = 'hangve/' . $date;
            $img = $path . '/' . $fileName;

            file_put_contents($img, file_get_contents($url));
            Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

            $pathName = '/' . $pathOSS . '/' . $fileName;
            $full_path =  env('OSS_URL') . $pathName;
            unlink($link);

            if ($file_first == null) {
                $file_first = $pathName;
            }
            array_push($thuongdo_files, $pathName);
        }

        if ($file_first) {
            $refundDB->file = $full_path;
            $refundDB->thuongdo_files = $thuongdo_files;
            $refundDB->save();
        }
    }
}
