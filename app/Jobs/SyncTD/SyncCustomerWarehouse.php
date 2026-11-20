<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;
use App\Constants\TransactionConstant;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\CustomerOffer;
use App\Constants\CustomerConstant;

class SyncCustomerWarehouse implements ShouldQueue
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

        \Log::info('SyncCustomerWarehouse: ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/customer';

        $options = [
            'multipart' => [
                [
                    'name'     => 'page',
                    'contents' => $page,
                ],
                [
                    'name'     => 'type_warehouse',
                    'contents' => 1,
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
            $customerModel = \App::make('App\Models\Customer');
            $warehouseModel = \App::make('App\Models\Warehouse');

            foreach ($result as $key => $item) {
                $customerDB = $customerModel->where('username', $item['username'])->first();

                if (!is_null($customerDB)) {

                    if ($item['warehouse_default_id'] == null) {
                        continue;
                    }

                    // Thêm phần kho nhận hàng
                    $warehouseDB = $warehouseModel->where('thuongdo_id', $item['warehouse_default_id'])->first();

                    if (!is_null($warehouseDB)) {
                        \Log::info('zzz:' . $customerDB->username);
                        $customerDB->warehouse_id = (int) $warehouseDB->id;
                        // \Log::info('WH_name: ' . $warehouseDB->address_other);
                        // \Log::info('WH_code: ' . $warehouseDB->code);
                        // \Log::info('WH_id: ' . $warehouseDB->thuongdo_id);
                        // \Log::info('TD_id: ' . $customerDB->thuongdo_id);
                        // \Log::info('TD_username: ' . $customerDB->username);
                        // \Log::info("---------");
                        $customerDB = Customer::withoutEvents(function () use ($customerDB) {
                            $customerDB->save();
                            return $customerDB;
                        });
                    }
                }
            }
        }
    }
}
