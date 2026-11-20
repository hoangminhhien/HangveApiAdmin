<?php

namespace App\Events\PlaceOrder;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaceOrderCreateLocalDataEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Dữ liệu người dùng gửi lên sau khi đã được format, dùng để gửi qua Buyder đặt hàng
     *
     * @var array
     */
    public array $inputData;

    /**
     * Dữ liệu nhận về từ Buyder sau khi đặt hàng
     *
     * @var array
     */
    public array $dataBuyder;

    /**
     * Phân biệt request gửi từ trang nào lên
     *   + place_order_list: Danh sách đặt hàng
     *   + place_order_assign_support: Đơn hàng hỗ trợ
     * Nếu là 'Đơn hàng hỗ trợ' thì phải cập nhật thêm 'assign_support_orders.order_no' (mã đặt hàng)
     *
     * @var string
     */
    public string $source;

    /**
     * Phân biệt đặt hàng ở nền tảng nào (taobao hoặc 1688)
     *
     * @var string
     */
    public string $platformSource;

    /**
     * Create a new event instance.
     *
     * @param array $inputData dữ liệu người dùng gửi lên sau khi đã được format, dùng để gửi qua Buyder đặt hàng
     * @param array $dataBuyder dữ liệu nhận về từ Buyder sau khi đặt hàng
     * @param string $source phân biệt request được gửi từ trang nào lên
     * @param string $platformSource đặt hàng trên platform nào (Taobao, 1688)
     * @return void
     */
    public function __construct(array $inputData, array $dataBuyder, string $source, string $platformSource)
    {
        $this->inputData = $inputData;
        $this->dataBuyder = $dataBuyder;
        $this->source = $source;
        $this->platformSource = $platformSource;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('place-order-create-local-data');
    }
}
