<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

class SyncSupplier implements ShouldQueue
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

        // \Log::info('SyncSupplier-V2 12092024: ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/supplier';

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
            $supplierModel = \App::make('App\Models\Supplier');
            foreach ($result as $key => $item) {
                $code = $item['ncc_code'];
                if (is_null($code)) {
                    $code = 'CC_' . \Str::random(12);
                }

                $name = $item['name'] == null ? 'null2023' : $item['name'];
                $data_supplier = [
                    'thuongdo_id'         => $item['id'],
                    'thuongdo_shop_id'    => $item['shop_id'],
                    'thuongdo_name_en'    => $item['name_en'],
                    'thuongdo_address_en' => $item['address_en'],
                    'thuongdo_page'       => $pageCron,
                    'name'                => $name,
                    'address'             => $item['shop_address'],
                    'website'             => $item['website'],
                    'logo'                => $item['logo'],
                    'code'                => $code,
                    'organization_id'     => 1
                ];

                $supplierDB = $supplierModel->where('thuongdo_id', $data_supplier['thuongdo_id'])->first();
                if (is_null($supplierDB)) {
                    $supplierNew = $supplierModel->create($data_supplier);
                } else {
                    $supplierDB->thuongdo_page = $pageCron;
                    $supplierDB->save();
                }
            }
        }

        // Nếu còn data thì tiếp tục lấy tiếp
        // if (count($result) > 0) {
        //     $pageNew = $page + 1;
        //     if ($pageNew < 2000000) {
        //         $job = new \App\Jobs\SyncTD\SyncSupplier($pageNew);
        //         dispatch($job)->delay(now()->addSeconds(0))->onQueue('cron-data-2023');
        //     }
        // }
    }
}
