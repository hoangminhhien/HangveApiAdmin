<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\OrderConstant;
use App\Http\Resources\EcommerceResource;
use App\Models\Ecommerce;
use App\Models\Order;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcommerceService extends AbstractBaseService
{
    protected string $_resource = EcommerceResource::class;
    protected function setModel()
    {
        $this->_model = new Ecommerce();
    }

    /**
     * @throws BindingResolutionException
     */
    public function sentNotificationOrderSlowQuote($params = [])
    {
        $key = Ecommerce::CACHE_KEY_ECOMMERCE;
        $lists = Cache::get($key, []);
        if (empty($lists)) {
            $lists = $this->index(['without_resource' => true])->toArray();
            Cache::put($key, $lists);
        }

        $orderQuery = Order::query()->withoutGlobalScopes();
        $orderQuery = $orderQuery->select(
            'id', 'status', 'code',
            'is_sent_notification',
            'staff_quotation_id', 'staff_group_id', 'created_at', 'ecommerce'
        );
        if (!empty($params['ecommerce'])) {
            // Log::info('ecommerce' . $params['ecommerce']);
            $orderQuery = $orderQuery->where('orders.ecommerce', $params['ecommerce']);
        }
        if (!empty($params['staff_quotation_id'])) {
            Log::info('staff_quotation_id' . $params['staff_quotation_id']);
            $orderQuery = $orderQuery->where('orders.staff_quotation_id', $params['staff_quotation_id']);
        }
        if (!empty($params['staff_group_id'])) {
            Log::info('staff_group_id' . $params['staff_group_id']);
            $orderQuery = $orderQuery->where('orders.staff_group_id', $params['staff_group_id']);
        }
        if (!empty($params['code'])) {
            Log::info('orders.code' . $params['code']);
            $orderQuery = $orderQuery->where('orders.code', $params['code']);
        }
        $orderQuery = $orderQuery->whereHas('orderStatusTime');
        $orderQuery = $orderQuery->with('orderStatusTime');
        $orderQuery = $orderQuery->where('status', OrderConstant::KEY_STATUS_WAITING_QUOTE);
        $orderQuery = $orderQuery->where('is_sent_notification',OrderConstant::IS_SENT_NOTIFICATION_FALSE);
        $orderQuery->chunk(500, function ($orders) use ($lists) {
            foreach ($orders as $order) {
                $isValidate = $this->checkTime($order, $lists);
                if ($isValidate) {
                    $data_order = [
                        'order_code' => $order->code,
                        'order_id' => $order->id,
                        'order_status' => $order->status,
                        'order_status_txt' => OrderConstant::STATUSES[$order->status],
                    ];
                    $content = 'Đơn hàng '. $order->code .' chưa được báo giá. Vui lòng kiểm tra ngay!';
                    $data_order['content'] = $content;
                    $name = 8001;
                    $order->is_sent_notification = OrderConstant::IS_SENT_NOTIFICATION_TRUE;
                    $order->save();
                    $staff_quotation_id = $order->staff_quotation_id ?? '';
                    $staff_group_id = $order->staff_group_id ?? '';
                    $data = [
                        'group' => 2,
                        'name' => $name,
                        'data' => json_encode($data_order),
                        'time_create' => time(),
                        'status' => 1
                    ];
                    if ($staff_group_id) {
                        (new NotificationService())->storeNoti($staff_group_id, $data);
                    }
                    if ($staff_quotation_id) {
                        (new NotificationService())->storeNoti($staff_quotation_id, $data);
                    }
                }
            }
        });
    }

    private function checkTime($order, $lists): bool
    {
        return checkTimeOrderSlowQuote($order, $lists);
    }
}
