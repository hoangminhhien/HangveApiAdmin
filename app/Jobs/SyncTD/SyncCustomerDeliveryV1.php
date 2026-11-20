<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

class SyncCustomerDeliveryV1 implements ShouldQueue
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

        \Log::info('SyncCustomerDelivery-V2 15022024: ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/user-delivery-address';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $page,
                ],
                [
                    'name'     => 'token',
                    'contents' => env('SYNC_TD_TOKEN'),
                ]
            ]
        ];

        $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        $pageCron = $page;

        if ($result && count($result)) {
            $customerModel = \App::make('App\Models\Customer');
            $customerDeliveryModel = \App::make('App\Models\CustomerDelivery');
            $provinceModel = \App::make('App\Models\Province');
            $districtModel = \App::make('App\Models\District');

            foreach ($result as $key => $item) {

                $default_check = $item['default_check'];
                if ($item['default_check'] == '') {
                    $default_check = 0;
                }


                if ($item['fullname'] == '') {
                    continue;
                }

                $address = $item['data'];
                if ($item['data'] == '') {
                    $address = 'null2023';
                }

                $phone_number = $item['phone'];
                if ($item['phone'] == '') {
                    $phone_number = 0;
                }

                // dd($item);
                $data_customer_delivey = [
                    'receiver'     => $item['fullname'],
                    'address'      => $address,
                    'phone_number' => $phone_number,
                    'is_default'   => $default_check,
                    'thuongdo_id'  => $item['did'],
                    'thuongdo_page'  => $pageCron,
                    'status'       => 1,
                ];

                $customerDeliveryDB = $customerDeliveryModel->where('thuongdo_id', $data_customer_delivey['thuongdo_id'])->first();

                if (is_null($customerDeliveryDB)) {
                    // Thêm phần tỉnh thành quận huyện
                    $provinceDB = $provinceModel->where('thuongdo_id', $item['tinh_id'])->first();
                    if (!is_null($provinceDB)) {
                        $data_customer_delivey['province_id'] = $provinceDB->id;
                    }
                    $districtDB = $districtModel->where('thuongdo_id', $item['quan_huyen_id'])->first();
                    if (!is_null($districtDB)) {
                        $data_customer_delivey['district_id'] = $districtDB->id;
                    }

                    // Thêm customer_id
                    $customerDB = $customerModel->where('thuongdo_uuid', $item['uid'])->first();
                    if (!is_null($customerDB)) {
                        $data_customer_delivey['customer_id'] = $customerDB->id;
                    } else {
                        \Log::info('Không tim khách');
                    }

                    // Check tìm dc customer_id mới tạo địa chỉ cho khách
                    if (isset($data_customer_delivey['customer_id'])) {
                        $customerDeliverysNew = $customerDeliveryModel->create($data_customer_delivey);
                    }
                } else {
                    $customerDeliveryDB->thuongdo_page = $pageCron;
                    $customerDeliveryDB->save();
                }
            }
        }

        // Nếu còn data thì tiếp tục lấy tiếp
        if (count($result) > 0) {
            $pageNew = $page + 1;
            if ($pageNew < 2000000) {
                $job = new \App\Jobs\SyncTD\SyncCustomerDelivery($pageNew);
                dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');
            }
        }
    }
}
