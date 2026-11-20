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

class SyncDateDonePackage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $page;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($page)
    {
        $this->page = $page;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $page = $this->page;

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/package';

        $pageCron = $page;
        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $pageCron,
                ],
                [
                    'name'     => 'month_to',
                    'contents' => '2018-01-01',
                ],
                [
                    'name'     => 'month_end',
                    'contents' => '2024-10-30',
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && count($result)) {
            $orderPackageModel = \App::make('App\Models\OrderPackage');
            $count = 0;
            foreach ($result as $key => $item) {
                if ($item['ngay_hoan_thanh']) {
                    $date_done = date('Y-m-d H:i:s', $item['ngay_hoan_thanh']);

                    $packDB = $orderPackageModel->where('thuongdo_id', $item['id'])
                                                ->where('status', 'status_16')
                                                ->first();
                    if (!is_null($packDB)) {
                        if ($packDB->date_done == null) {
                            // Lưu lại
                            $count += 1;
                            $packDB->date_done = $date_done;
                            $packDB = OrderPackage::withoutEvents(function () use ($packDB) {
                                $packDB->save();
                                return $packDB;
                            });
                        }
                    }
                }
            }
            \Log::info('syncUpdateDateDonePackage: V1 - ' . $pageCron . ' - ' . $count);
        }
    }
}
