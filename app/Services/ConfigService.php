<?php

namespace App\Services;

use App\Actions\Config\ReceiverAction;
use App\Actions\Config\ShippingEstimatedFeeAction;
use App\Constants\ConfigConstant;
use App\Http\Resources\Config\ListConfigResource;
use App\Models\Config;
use App\Models\ReasonConfirmOrder;
use Illuminate\Http\JsonResponse;

class ConfigService extends BaseService
{
    protected string $_listResource = ListConfigResource::class;

    /**
     * @param  string  $key
     * @return mixed
     */
    public function getValueByKey(string $key)
    {
        return optional(Config::query()->where('key', $key)->first())->value;
    }

    /**
     * @param  string  $key
     * @param  float  $v
     * @return mixed
     */
    public function getResultFromValueByBetweenMinMax(string $key, float $v, $values = null)
    {
        $result = null;
        if($values == null) {
            $values = json_decode($this->getValueByKey($key));
        }

        foreach ($values as $key => $value) {
            // Lấy giá trị mặc định cho dịch vụ kiểm hàng
            if (isset($value->default)) {
                $result = $value->default;
                continue;
            }
            if ((float)$value->min <= $v && (float)$value->max > $v) {
                $result = $value->result;
                break;
            }
        }
        return $result;
    }

    public function getResultFromValueByAdvancedInspection(string $key)
    {
        $result = null;
        $values = json_decode($this->getValueByKey($key));
        if (!$values) {
            return 0;
        }
        foreach ($values as $value) {
            if ((float)$value->min == (float)$value->max ) {
                $result = $value->result;
                break;
            }
        }
        return $result;
    }

    /**
     * @param  float  $level
     * @return mixed
     */
    public function getResultFromValueByLevel(float $level)
    {
        $values = json_decode($this->getValueByKey(ConfigConstant::CUSTOMER_LEVEL));
        foreach ($values as $key => $value) {
            if ($value->level == $level) {
                return $value->result;
            }
        }
        return null;
    }
    public function getLevelFromValue($amount)
    {
        $values = json_decode($this->getValueByKey(ConfigConstant::CUSTOMER_LEVEL));
        $result = 0;
        foreach ($values as $value) {
            if ((float)$value->min < $amount && (float)$value->max >= $amount) {
                $result = $value->level;
            }
        }
        if ($result > 1) {
            return $result;
        }
        return 1;
    }

    public function getExchangeRate(): float
    {
        return (float)$this->getValueByKey(ConfigConstant::CURRENCY_EXCHANGE_RATE);
    }

    /**
     * @param  float  $cost
     * @return null
     */
    public function getLevelByCosts(float $cost): int
    {
        $values = json_decode($this->getValueByKey(ConfigConstant::CUSTOMER_LEVEL));
        foreach ($values as $key => $value) {
            if ($value->min <= $cost && $value->max >= $cost) {
                return $value->level;
            }
        }
        return array_pop($values)->level;
    }

    /**
     * Lấy danh sách địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function listReceivers(array $params = []): JsonResponse
    {
        $action = app()->make(ReceiverAction::class);

        return $action->handle(array_merge($params, [
            'method' => __FUNCTION__
        ]));
    }

    /**
     * Cập nhật địa chỉ nhận hàng
     *
     * @return JsonResponse
     */
    public function updateReceivers(array $params): JsonResponse
    {
        $action = app()->make(ReceiverAction::class);

        return $action->handle(array_merge($params, [
            'method' => __FUNCTION__
        ]));
    }

    // notification sửa tỉ giá
    public function sendNotiForCustomerWhenChangeExchangeRate($value, $value_old)
    {
        $notiService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'exchange_rate'     => $value,
            'exchange_rate_old'     => $value_old,
        ];

        $data = [
            'group'           => 12,
            'name'            => 12000,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'status'          => 1
        ];

        $data['organization_id']  = getOrganization();



        $notiService->storeNotiChangeExchangeRate($data);


    }

    /**
     * Lấy danh sách config phí vận chuyển tạm tính
     *
     * @return JsonResponse
     */
    public function getConfigShippingEstimatedFee(): JsonResponse
    {
        $action = app()->make(ShippingEstimatedFeeAction::class);
        return $action->handle([
            'method' => __FUNCTION__
        ]);
    }

    /**
     * Chỉnh sửa config phí vận chuyển tạm tính
     *
     * @return JsonResponse
     */
    public function updateConfigShippingEstimatedFee(array $data): JsonResponse
    {
        $action = app()->make(ShippingEstimatedFeeAction::class);
        return $action->handle(array_merge($data, [
            'method' => __FUNCTION__
        ]));
    }

    /**
     * Lấy danh sách đơn vị (đo lường và đếm)
     *
     * @return JsonResponse
     */
    public function getConfigUnits(): JsonResponse
    {
        $action = app()->make(ShippingEstimatedFeeAction::class);
        return $action->handle([
            'method' => __FUNCTION__
        ]);
    }

    public function storeReasonConfirmOrder(array $data)
    {
        $data['time'] = json_encode($data['time']);
        return ReasonConfirmOrder::query()->create($data);
    }

    public function updateReasonConfirmOrder($id, array $data)
    {
        $data['time'] = json_encode($data['time']);
        return ReasonConfirmOrder::where('id', $id)->update($data);
    }

    public function destroyReasonConfirmOrder(string $id): JsonResponse
    {
        ReasonConfirmOrder::findOrFail($id)->delete();
        return resSuccess(trans('system.deleted'));
    }
}
