<?php


namespace App\Services\Carriers;

use App\Constants\CarrierConstant;
use App\Models\CustomerDelivery;
use App\Models\Warehouse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ViettelService implements Carrier
{

    private string $_url;
    private string $_login;
    private string $_price;
    private string $_order;
    private string $_certificate;

    public function __construct()
    {
        $this->_url = config('carrier.viettel');
        $this->_login = $this->_url.CarrierConstant::ENDPOINT_VIETTEL_LOGIN;
        $this->_price = $this->_url.CarrierConstant::ENDPOINT_VIETTEL_PRICE;
        $this->_order = $this->_url.CarrierConstant::ENDPOINT_VIETTEL_ORDER;
        $this->_certificate = json_encode((new CarrierService())->getConfigByCarrier(CarrierConstant::VIETTEL));
    }

    public function getPrice(array $array): float
    {
        $body = $this->getParamsPrice($array);
        try {
            $response = Http::withHeaders($this->getHeaders())->withBody(
                json_encode($body),
                'application/json'
            )->post(
                $this->_price
            )->throw()->json();
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, $response);
            if ($response['status'] !== 200) {
                return self::FAIL;
            }
            return $response['data']['MONEY_TOTAL_FEE'];
        } catch (RequestException $e) {
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::FAIL;
        }
    }

    public function getHeaders(): array
    {
        return [
            'token' => $this->getToken()
        ];
    }

    public function getParamsPrice(array $array): ?array
    {
        /** @var Warehouse $warehouse */
        /** @var CustomerDelivery $delivery */
        /** @var Collection $packages */
        [$packages, $warehouse, $delivery] = (new CarrierService())->getVariables(
            $array['warehouse_id'],
            $array['packages'],
            $array['customer_delivery_id']
        );
        return [
            "PRODUCT_WEIGHT" => (clone $packages)->sum('weight'),
            "PRODUCT_PRICE" => (clone $packages)->sum('order_cost'),
            "MONEY_COLLECTION" => 0,
            "ORDER_SERVICE_ADD" => "",
            "ORDER_SERVICE" => $array['delivery_type'] === 'fast' ? "VCN" : 'VCB',
            "SENDER_PROVINCE" => (string)$warehouse->province->viettel_id,
            "SENDER_DISTRICT" => (string)$warehouse->district->viettel_id,
            "RECEIVER_PROVINCE" => (string)$delivery->province->viettel_id,
            "RECEIVER_DISTRICT" => (string)$delivery->district->viettel_id,
            "PRODUCT_TYPE" => "HH",
            "NATIONAL_TYPE" => 1
        ];
    }

    /**
     * @return string|null
     */
    private function getToken(): ?string
    {
        //eyJhbGciOiJFUzI1NiJ9.eyJzdWIiOiIwOTY1Mzk4OTk4IiwiVXNlcklkIjoxNDM3MTkzLCJGcm9tU291cmNlIjo1LCJUb2tlbiI6IkM3UjJVV0pUT0lQRkkiLCJleHAiOjE2Nzk2NDkzMzksIlBhcnRuZXIiOjE0MzcxOTN9.J9c6o4agMpRh2QHE85BihFdwGdgst7WVUsKmAfinnfE0A1kAfPGT7iA68I4Awx0Mwaamdk1RA7uVm_iTe7jb6g
        try {
            $response = Http::withBody($this->_certificate, 'application/json')->post($this->_login)->throw()->json();
            return $response['data']['token'];
        } catch (RequestException $e) {
            return null;
        }
    }

    public function createOrder(array $array)
    {
        $body = $this->getParamsOrder();
        try {
            $response = Http::withHeaders($this->getHeaders())->withBody(
                json_encode($body),
                'application/json'
            )->post(
                $this->_order
            )->throw()->json();
            dd($response);
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, $response);
            if ($response['status'] !== 200) {
                return self::FAIL;
            }
            return $response['data']['MONEY_TOTAL_FEE'];
        } catch (RequestException $e) {
            (new CarrierService())->setActivity($array['customer_delivery_id'], $body, [$e->getMessage()]);
            return self::DATA_FORMAT_WRONG;
        }
    }

    private function getParamsOrder(): array
    {
        return [
            "ORDER_NUMBER" => "12",
            "GROUPADDRESS_ID" => 5818802,
            "CUS_ID" => 722,
            "DELIVERY_DATE" => "11/10/2018 15:09:52",
            "SENDER_FULLNAME" => "Yanme Shop",
            "SENDER_ADDRESS" => "Số 5A ngách 22 ngõ 282 Kim Giang, Đại Kim (0967.363.789), Quận Hoàng Mai, Hà Nội",
            "SENDER_PHONE" => "0967.363.789",
            "SENDER_EMAIL" => "vanchinh.libra@gmail.com",
            "SENDER_WARD" => 0,
            "SENDER_DISTRICT" => 4,
            "SENDER_PROVINCE" => 1,
            "SENDER_LATITUDE" => 0,
            "SENDER_LONGITUDE" => 0,
            "RECEIVER_FULLNAME" => "Hoàng - Test",
            "RECEIVER_ADDRESS" => "1 NKKN P.Nguyễn Thái Bình, Quận 1, TP Hồ Chí Minh",
            "RECEIVER_PHONE" => "0907882792",
            "RECEIVER_EMAIL" => "hoangnh50@fpt.com.vn",
            "RECEIVER_WARD" => 0,
            "RECEIVER_DISTRICT" => 43,
            "RECEIVER_PROVINCE" => 2,
            "RECEIVER_LATITUDE" => 0,
            "RECEIVER_LONGITUDE" => 0,
            "PRODUCT_NAME" => "Máy xay sinh tố Philips HR2118 2.0L ",
            "PRODUCT_DESCRIPTION" => "Máy xay sinh tố Philips HR2118 2.0L ",
            "PRODUCT_QUANTITY" => 1,
            "PRODUCT_PRICE" => 2292764,
            "PRODUCT_WEIGHT" => 40000,
            "PRODUCT_LENGTH" => 38,
            "PRODUCT_WIDTH" => 24,
            "PRODUCT_HEIGHT" => 25,
            "PRODUCT_TYPE" => "HH",
            "ORDER_PAYMENT" => 3,
            "ORDER_SERVICE" => "VCN",
            "ORDER_SERVICE_ADD" => "",
            "ORDER_VOUCHER" => "",
            "ORDER_NOTE" => "cho xem hàng, không cho thử",
            "MONEY_COLLECTION" => 2292764,
            "MONEY_TOTALFEE" => 0,
            "MONEY_FEECOD" => 0,
            "MONEY_FEEVAS" => 0,
            "MONEY_FEEINSURRANCE" => 0,
            "MONEY_FEE" => 0,
            "MONEY_FEEOTHER" => 0,
            "MONEY_TOTALVAT" => 0,
            "MONEY_TOTAL" => 0,
            "LIST_ITEM" => [
                [
                    "PRODUCT_NAME" => "Máy xay sinh tố Philips HR2118 2.0L ",
                    "PRODUCT_PRICE" => 2150000,
                    "PRODUCT_WEIGHT" => 2500,
                    "PRODUCT_QUANTITY" => 1
                ]
            ]
        ];
    }
}