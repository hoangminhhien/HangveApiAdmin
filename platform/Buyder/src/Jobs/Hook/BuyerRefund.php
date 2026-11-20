<?php

namespace ThuongDo\Buyder\src\Jobs\Hook;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyerRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Input data, là data nhận từ Buyder (Buyder chuyển hết từ Pandapon sang)
     *
     * @var array
     */
    private array $data;
    /**
     * Nguồn đặt hàng (Taobao, 1688)
     *
     * @var string
     */
    private string $source;

    /**
     * Create a new job instance
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->source = data_get($data, 'source');
    }

    /**
     * Execute the job
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::transaction(function (): void {
                $method = 'buyerRefund' . ucfirst($this->source);
                $this->{$method}();
            });
        } catch (\Exception $e) {
            Log::error('Lỗi BuyerRefund: ' . $e);
        }
    }

    private function buyerRefundTaobao(): void
    {
        Log::info('BuyerRefund Taobao');
    }

    private function buyerRefund1688(): void
    {
        Log::info('BuyerRefund 1688');
    }
}
