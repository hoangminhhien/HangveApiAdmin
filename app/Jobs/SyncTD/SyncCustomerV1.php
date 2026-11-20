<?php

namespace App\Jobs\SyncTD;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as Guzzle;

class SyncCustomerV1 implements ShouldQueue
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

        \Log::info('SyncPage ' . $page);

        $guzzle = new Guzzle;
        $url = env('SYNC_TD_URL') . 'convert/customer';

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

        if ($result && count($result)) {
            foreach ($result as $key => $item) {
                $data_customer = [
                    'name'  => $item['fullname'],
                    'email' => $item['mail'],
                    'phone_number' => $item['phone'],
                ];
                // \Log::info('Lưu thí sinh' . $item['mail']);
                if ($item['nhan_vien_dat_hang_id']) {
                    \Log::info('Lưu thí sinh' . $item['nhan_vien_dat_hang_id']);
                }
            }

            // Nếu còn data thì tiếp tục lấy tiếp
            if (count($result) > 0) {
                $pageNew = $page + 1;
                $job = new \App\Jobs\SyncTD\SyncCustomer($pageNew);
                dispatch($job)->delay(now()->addSeconds(1))->onQueue('sent-noti-admin'); // Dùng chung đỡ phải cấu hình
            }
        }
    }
}
