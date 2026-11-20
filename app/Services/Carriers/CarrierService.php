<?php


namespace App\Services\Carriers;

use App\Constants\CarrierConstant;
use App\Constants\ConfigConstant;
use App\Models\CarrierActivity;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\OrderPackage;
use App\Models\Transporter;
use App\Models\Warehouse;
use App\Services\ConfigService;
use App\Services\Service;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Collection;

class CarrierService implements Service
{
    public function getPrice(array $data): float
    {
        switch ($data['carrier']) {
            case CarrierConstant::GHTK:
                return (new GHTKService())->getPrice($data);
            case CarrierConstant::GHN:
                return (new GHNService())->getPrice($data);
            case CarrierConstant::VIETTEL:
                return (new ViettelService())->getPrice($data);
            default:
                return -1;
        }
    }

    /**
     * @param string $carrier
     * @return mixed
     */
    public function getConfigByCarrier(string $carrier)
    {
        return json_decode((new ConfigService())->getValueByKey(ConfigConstant::CARRIERS))->{$carrier};
    }

    /**
     * @param string $warehouseId
     * @param array $packages
     * @param string $customerDeliveryId
     * @return array
     */
    public function getVariables(string $warehouseId, array $packages, string $customerDeliveryId): array
    {
        $packages = OrderPackage::query()->findMany($packages);

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        /** @var CustomerDelivery $delivery */
        $delivery = CustomerDelivery::query()->findOrFail($customerDeliveryId);

        return [$packages, $warehouse, $delivery];
    }

    /**
     * @param string $deliveryId
     * @param array $body
     * @param array $response
     */
    public function setActivity(string $deliveryId, array $body, array $response): void
    {
        CarrierActivity::query()->create(
            [
                'customer_delivery_id' => $deliveryId,
                'body'                 => json_encode($body),
                'response'             => json_encode($response)
            ]
        );
    }

    public function createOrder(array $data = [])
    {
        $delivery             = $data['deliveryId'] ? Delivery::query()->find($data['deliveryId']) : null;
        $transporterShortName = $delivery->transporter->short_name ?? null;
        switch ($transporterShortName) {
            case CarrierConstant::GHTK:
                return (new GHTKService())->createOrder($data);
            case CarrierConstant::GHN:
                return (new GHNService())->createOrder($data);
            case CarrierConstant::VIETTEL:
                return (new ViettelService())->createOrder($data);
            default:
                return null;
        }
    }

    public function cancelOrder(array $data = [])
    {
        switch ($data['carrier_short_name']) {
            case CarrierConstant::GHTK:
                return (new GHTKService())->cancelOrder($data);
            case CarrierConstant::GHN:
                return (new GHNService())->cancelOrder($data);
            case CarrierConstant::VIETTEL:
                return true;
            default:
                return null;
        }
    }

    public function sendNotificationCreateOrCancelCarrier($delivery, $type = "create")
    {
        $notificationService = \App::make('App\Services\NotificationService');
        $transporter = $delivery->transporter;
        $data_delivery = [
            'delivery_id'         => data_get($delivery, 'id'),
            'delivery_code'       => data_get($delivery, 'code'),
            'delivery_postcode'   => data_get($delivery, 'postcode'),
            'delivery_status'     => data_get($delivery, 'status'),
            'delivery_status_txt' => \App\Constants\DeliveryConstant::STATUSES[data_get($delivery, 'status')],
        ];
        if($type == "create"){
            $data_delivery = array_merge($data_delivery, [
                'transporter_name' => $transporter ? $transporter->name : null
            ]);
        }else{
            $data_delivery = array_merge($data_delivery, [
                'cancel_by' => \Auth::user()->name
            ]);
        }
        $data = [
            'group'           => 4,
            'name'            => $type == "create" ? 4003 : 4004,
            'data'            => json_encode($data_delivery),
            'time_create'     => time(),
            'status'          => 1
        ];
        if(data_get($delivery, 'staff_care_id')){
            $notificationService->storeNoti($delivery->staff_care_id, $data);
        }
        if(data_get($delivery, 'staff_counselor_id')){
            $notificationService->storeNoti($delivery->staff_counselor_id, $data);
        }
    }
}
