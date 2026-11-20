<?php

namespace App\Http\Resources\Report;

use App\Constants\CustomerConstant;
use App\Constants\OrderConstant;
use App\Constants\ViaConstant;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPotentialResource extends JsonResource
{
    public function toArray($request): array
    {
        $orderLast = $this->getLastOrder(false);
        $orders = optional($this->orders);
        $orderDeposit = $orders;
        $totalOrdersDeposit = $orderDeposit ? $orderDeposit->count() : 0;

        $arr_point_way = $this->getPointTime();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone_number,
            'email' => $this->email,
            'point' => $this->point_way,
            'point_care' => $this->point_care,
            'chatbiz_total_call' => $this->chatbiz_total_call,
            'last_order' => $orderLast ? date('d/m/Y', strtotime($orderLast)) : '',
            'orders_deposit' => $totalOrdersDeposit,
            'categories_price' => $totalOrdersDeposit ? $this->getCategoryPrice($orderDeposit) : [],
            'deposit_level' => $totalOrdersDeposit ? $this->getLevelDeposit($orderDeposit) : '',
            'staff_group_name' => data_get($this->staffGroup, 'name'),
            'business_type_text' => $this->getBusinessTypeText(),
            'via_text' => $this->getViaText(),
            'source' => $this->source ?? '',
            'source_name' => Customer::LIST_SOURCE[$this->source] ?? '',
            'arr_point_way_1' => $arr_point_way[1],
            'arr_point_way_2' => $arr_point_way[2],
            'arr_point_way_3' => $arr_point_way[3],
            'arr_point_way_4' => $arr_point_way[4],
            'arr_point_way_5' => $arr_point_way[5],
            'arr_point_way_6' => $arr_point_way[6],
            'arr_point_way_7' => $arr_point_way[7],
            'arr_point_way_8' => $arr_point_way[8],
            'arr_point_way_9' => $arr_point_way[9],
            'arr_point_way_10' => $arr_point_way[10],
            'err_chat' => $this->getErrorChat()
        ];
    }

    public function getListCategories($orderDeposit)
    {
        $categories = [];

        $orderDeposits = $orderDeposit->all();

        foreach ($orderDeposits as $item) {
            $orderDetails = !empty($item->details) ? $item->details : [];
            foreach ($orderDetails as $orderDetail) {
                $categories[] = optional($orderDetail->category)->name;
            }
        }

        return implode(', ', array_unique($categories));
    }

    public function getCategoryPrice($orderDeposit)
    {
        $categoryModel = \App::make('App\Models\Category');
        $warehouseModel = \App::make('App\Models\Warehouse');
        $categories = [];

        $code = null;
        $warehouseDB = $warehouseModel->where('id', $this->warehouse_id)
                                        ->where('country', 'vi')
                                        ->where('status', 1)->first();
        if (!is_null($warehouseDB)) {
            $code = 'HN';
            if ($warehouseDB->code == 'HCM') {
                $code = 'HCM';
            }
        }

        $orderDeposits = $orderDeposit->all();
        foreach ($orderDeposits as $item) {
            $orderDetails = !empty($item->details) ? $item->details : [];
            foreach ($orderDetails as $orderDetail) {
                $category_name = optional($orderDetail->category)->name;
                if (!is_null($category_name)) {
                    $categoryDB = $categoryModel->where('name', $category_name)->first();
                    $priceKg = number_format($categoryDB->hn_kg,2,",",".") . '/kg';
                    $priceM3 = number_format($categoryDB->hn_m3,2,",",".") . '/m3';
                    if ($code == "HN") {
                        $category_name .= ': (HN - ' . $priceKg . '; ' . $priceM3 .')';
                    }

                    $priceKg = number_format($categoryDB->hcm_kg,2,",",".") . '/kg';
                    $priceM3 = number_format($categoryDB->hcm_m3,2,",",".") . '/m3';
                    if ($code == "HCM") {
                        $category_name .= ': (HCM - ' . $priceKg . '; ' . $priceM3 .')';
                    }

                    array_push($categories, $category_name);
                }
            }
        }

        return array_unique($categories);
    }

    public function getLevelDeposit($orderDeposit)
    {
        $level = '';

        $orderDepositFirst = $orderDeposit->first();
        $orderStatusTime   = optional($orderDepositFirst->orderStatusTime)->{OrderConstant::KEY_STATUS_WAITING_DEPOSIT};

        if (!$orderStatusTime) {
            return $level;
        }

        $timeStart = strtotime($orderStatusTime) + 5 * 60;
        $timeEnd   = strtotime($orderStatusTime) + 60 * 60;
        if (time() <= $timeStart) {
            $level = CustomerConstant::WAIT_DEPOSIT_LV1;
        } else if (time() > $timeStart && time() <= $timeEnd) {
            $level = CustomerConstant::WAIT_DEPOSIT_LV2;
        } else { // over 60 minutes
            $level = CustomerConstant::WAIT_DEPOSIT_LV3;
        }

        return $level;
    }

    public function getPointTime()
    {
        for ($i=0; $i <= 10; $i++) {
            $arr_point_time[$i] = '';
        }
        $pointWay = $this->pointWay;
        if (count($pointWay)) {
            foreach ($pointWay as $key => $point) {
                $case = $point->case;
                $index = $case - 1001;
                $time = $point->created_at->format('Y-m-d H:i');
                $arr_point_time[$index * 2 + 1] = $point->created_at->format('d/m/Y');
                $arr_point_time[$index * 2 + 2] = $point->created_at->format('H:i');
            }
        }

        return $arr_point_time;
    }

    public function getBusinessTypeText()
    {
        $list = CustomerConstant::CUSTOMER_BUSINESS_TYPE;
        return array_key_exists($this->business_type, $list) ? $list[$this->business_type] : null;
    }

    public function getViaText()
    {
        $list = ViaConstant::STATUSES;
        return array_key_exists($this->via, $list) ? $list[$this->via] : 'Không xác định';
    }

    public function getErrorChat()
    {
        $conversationModel = \App::make('App\Models\Conversation');
        $conversationDB_count = $conversationModel->where('customer_id', $this->id)
                                                    ->where('conversation_config_id', 1)
                                                    ->count();
        if ($conversationDB_count > 0) {
            return 'Khách hàng nhắn tin chưa phản hồi';
        }

        return null;
    }
}
