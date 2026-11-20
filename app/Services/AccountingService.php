<?php


namespace App\Services;


use App\Constants\ConfigConstant;
use App\Constants\OrderConstant;
use App\Helpers\AccountingHelper;
use App\Models\Customer;
use App\Models\Province;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\OrderPackage;
use App\Models\ConfigLog;
use App\Constants\PackageConstant;

class AccountingService extends BaseService
{
    private ConfigService $_configService;

    public function __construct()
    {
        parent::__construct();
        $this->_configService = new ConfigService();
    }

    /**
     * @param  array  $data
     */
    public function calculatorServiceCost(array &$data)
    {
        $data['exchange_rate'] = (new ConfigService())->getExchangeRate();
        $data['total_order'] = $this->getTotalOrderInProducts($data['products'], $data['exchange_rate']);
        $data['inspection_cost'] = $data['is_inspection'] ? $this->getInspectionCost(array_sum($data['products'])) : 0;
        $data['discount_cost'] = $this->getCustomerLevelCost($data['total_order'], optional(Auth::user())->level ?? 1);
        $data['order_fee'] = $this->getOrderFee($data['total_order']);
    }

    /**
     * Tính phí dịch vụ kiểm hàng
     * @param  int  $productsNumber
     * @return float
     */
    public function getInspectionCost(int $productsNumber): float
    {
        return (float)$this->_configService->getResultFromValueByBetweenMinMax(
                ConfigConstant::SERVICE_INSPECTION,
                $productsNumber
            ) * $productsNumber;
    }

    public function getAdvancedInspectionCost(int $productsNumber): float
    {
        return AccountingHelper::getCosts((float)$this->_configService->getResultFromValueByAdvancedInspection(
                ConfigConstant::SERVICE_INSPECTION
            ) * $productsNumber);
    }

    public function getLevelCustomer(int $totalAmount)
    {
        return $this->_configService->getLevelFromValue(
            $totalAmount
        );
    }

    /**
     * @param  float  $weight
     * @param  float  $volume
     * @param  bool  $isWeightGreaterThanVolume
     * @return float
     */
    public function getWoodworkingCost(float $weight, float $volume, $package = null): float
    {
        if($weight == 0 && $volume == 0) return 0;
        // Nếu có config ở lúc tạo đơn thì lấy theo config luôn, không có thì lấy ở bảng config
        if(isset($package) && $package->configs) {
            $data = json_decode($package->configs);
            $weightJson = $data->woodworking_weight[0];
            $volumeJson = $data->woodworking_volume[0];
            $volumeJson = $this->_configService->getResultFromValueByBetweenMinMax(
                ConfigConstant::SERVICE_WOODWORKING_VOLUME,
                $volume,
                $volumeJson
            );
        } else {
            $weightJson = json_decode($this->_configService->getValueByKey(ConfigConstant::SERVICE_WOODWORKING_WEIGHT));
            $volumeJson = $this->_configService->getResultFromValueByBetweenMinMax(
                ConfigConstant::SERVICE_WOODWORKING_VOLUME,
                $volume
            );
        }
        $weight = roundHalfUp($weight);
        $cost = $this->getCostByFirstSubsequent($weightJson, $weight);
        $volume = $this->getVolumnStep($volume); // Chế cháo
        $volumeCost = $this->getCostByFirstSubsequent($volumeJson, $volume);
        return $package->type_quantitative == 'weight' ? $cost : $volumeCost;
    }

    public function getVolumnStep($volume)
    {
        if ($volume >= 0.01 && $volume < 0.1) {
            return $volume / 0.01;
        }
        if ($volume >= 0.1 && $volume < 1) {
            return $volume / 0.1;
        }
        if ($volume >1) {
            return $volume / 1;
        }
        return $volume;
    }

    /**
     * Phí vận chuyển quốc tế (Trung Quốc)
     * @param  string  $province
     * @param  float  $weight
     * @param  float  $volume
     * @param  bool|null  $isWeight
     * @param  string|null  $customerId
     * @return float
     */
    public function getInternationShippingCost(string $province, float $weight, float $volume, ?string $customerId = null,
        ?bool &$isWeight = true, $pack_id = null): float
    {
        // Đây là logic mới từ sau ngày 17-11-2023
        $weight = roundHalfUp($weight);
        // $volume = roundXPrecision($volume);
        if($weight == 0 && $volume == 0) {
            return 0;
        };

        $weightCost = $this->getCostByProvinceV2(ConfigConstant::TYPE_KG, $weight, $province, $customerId, $pack_id);
        $volumeCost = $this->getCostByProvinceV2(ConfigConstant::TYPE_M3, $volume, $province, $customerId, $pack_id);
        $isWeight = $weightCost >= $volumeCost;

        // Logic 11-10-2024:
        // Với kiện < 5Kg thì mặc định lấy theo kg luôn, còn lại về logic cũ
        if ($weight > 0 && $weight < 5) {
            $isWeight = true;

            // Logic 12-11-2024:
            // Nếu thể tích lớn hơn trọng lượng 30K thì lấy theo thể tích, còn lại theo kg
            $diffKgVolumn = $volumeCost - $weightCost;
            if ($diffKgVolumn > 30000) {
                $isWeight = false;

                // Logic 21-11-2024:
                // Nếu kg <= 2 thì lại tính KG
                if ($weight <= 2) {
                    $isWeight = true;
                }
            }
        }
        return AccountingHelper::getCosts($isWeight ? $weightCost : $volumeCost);
    }

    /**
     * @param  string  $key
     * @param  float  $value
     * @param  string  $province
     * @param  string|null $customerId
     * @return float
     */
    private function getCostByProvince(string $key, float $value, string $province, $customerId = null): float
    {
        $json                = $this->_configService->getResultFromValueByBetweenMinMax($key, $value);
        $costByProvince      = $json && isset($json->{$province}) ? $json->{$province} : 0;
        $costByCustomerOffer = $customerId ? $this->getCostCustomerOffer($key, $province, $customerId) : 0;
        $cost                = $costByCustomerOffer ? $costByCustomerOffer : $costByProvince;

        return $cost ? ($cost * $value) : 0;
    }

    // Hàm này dùng để lấy theo logic tính từ ngày 17-11-2023
    private function getCostByProvinceV2(string $type, float $value, string $province, $customerId = null, $pack_id = null): float
    {
        $costByProvince = 0; // Theo công thức admin cấu hình
        $costByCustomerOffer = 0; // Theo khách
        $dateCurrent = date('Y-m-d H:i:s', time());

        // Theo khách
        $province_name = str_replace('-', '', Str::slug(optional(Province::query()->find($province))->name));
        $key = ConfigConstant::SERVICE_PACKAGE_SHIPPING_WEIGHT;
        if ($type == ConfigConstant::TYPE_M3) {
            $key = ConfigConstant::SERVICE_PACKAGE_SHIPPING_VOLUME;
        };
        $packDB = OrderPackage::where('id', $pack_id)->first();
        $feeInternationalOfficialM3 = getFeeInternationalOfficialM3();
        if($packDB->official == OrderPackage::OFFICIAL_BCN) {
            // Ưu đãi chỉ áp dụng với kiện bán chính ngạch
            $costByCustomerOffer = $customerId ? $this->getCostCustomerOffer($key, $province_name, $customerId) : 0;
            if ($costByCustomerOffer) {
                return $costByCustomerOffer * $value;
            }
        }else {
            // Bổ sung ưu đãi cho kiện chính ngạch
            if($packDB->official == OrderPackage::OFFICIAL_CN) {
                $costByCustomerOffer = $customerId ? $this->getCostCustomerOfferCN($key, $province_name, $customerId) : 0;
                if ($costByCustomerOffer) {
                    return $costByCustomerOffer * $value;
                }
            }

            if($type == 'kg' && $packDB->unit_price_international_kg > 0) {
                return $packDB->unit_price_international_kg * $value;
            }
            if($type == 'm3' && $packDB->unit_price_international_m3 > 0) {
                return $packDB->unit_price_international_m3 * $value;
            }
        }
        $official = data_get($packDB, 'official') ?? OrderPackage::OFFICIAL_BCN;
        // Tính giá theo công thức
        $configInternationalShipModel = \App::make('App\Models\ConfigInternationalShip');

        $configInternationalShip = $configInternationalShipModel
                                                        ->where('official', $official)
                                                        ->where('type', $type)
                                                        ->where('date_start', '<', $dateCurrent)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();
        // Thêm logic kiện nhập kho trước ngày config mới nhất thì lấy theo bảng giá cũ
        if (!is_null($configInternationalShip)) {

            if (!is_null($pack_id)) {
                $date_check = $configInternationalShip->date_start->format('Y-m-d H:i:s');
                $orderPackageStatusTimeModel = \App::make('App\Models\OrderPackageStatusTime');
                $arr_status = [
                    PackageConstant::STATUS_IN_GUANGZHOU,
                    PackageConstant::STATUS_IN_PINGXIANG,
                    PackageConstant::STATUS_IN_CHINA,
                ];
                $timeCheckDB = $orderPackageStatusTimeModel->where('order_package_id', $pack_id)
                                                            ->whereIn('key', $arr_status)
                                                            ->orderBy('created_at', 'desc')
                                                            ->first();
                if (!is_null($timeCheckDB)) {
                    $configInternationalShipNew =  $configInternationalShipModel
                                                        ->where('official', $official)
                                                        ->where('type', $type)
                                                        ->where('date_start', '<', $timeCheckDB->created_at)
                                                        ->orderBy('date_start', 'desc')
                                                        ->first();
                    if (!is_null($configInternationalShipNew)) {
                        $configInternationalShip = $configInternationalShipNew;
                    }
                    // Lấy phí VCQT theo thời gian đến kho TQ
                    $config = ConfigLog::where('type', ConfigConstant::FEE_INTERNATIONAL_OFFICIAL_M3)
                                                        ->where('created_at', '<', $timeCheckDB->created_at)
                                                        ->orderBy('created_at', 'desc')
                                                        ->first();
                    $feeInternationalOfficialM3 = data_get($config, 'value_new') ??  $feeInternationalOfficialM3;
                }
            }
        }

        if (!is_null($configInternationalShip)) {
            // Tìm vị trí tỉnh thành đang ở vị trí bao nhiêu để dò vào ma trận giá
            $listProvinceArr = json_decode($configInternationalShip->list_province, true);
            $indexProvince = array_search ($province, $listProvinceArr);

            // Nếu ko tra index thì nghĩa là chưa có bảng giá cho tỉnh ấy, ko cần tính toán nữa
            if ($indexProvince >= 0) {
                $indexGetPrice = $indexProvince + 3; // Phải bỏ qua 3 phần tử đầu tiên của ma trận

                // Quy tắc là > min và <= max
                $matrixPriceArr = json_decode($configInternationalShip->matrix_price, true);
                foreach ($matrixPriceArr as $key_ranger => $ranger) {
                    if ($value > $ranger[1] && $value <= $ranger[2]) {
                        $costByProvince =  (int) $ranger[$indexGetPrice];
                    }
                }
            }
        }

        // So sánh
        //$cost = $costByCustomerOffer ? $costByCustomerOffer : $costByProvince;

        // Trả về
        $internationalCost = $costByProvince ? ($costByProvince * $value) : 0;
        if($packDB->official == OrderPackage::OFFICIAL_CN) {
            // Điều kiện để áp dụng phí vận chuyển chính ngạch tối thiểu
            if($value > 0) {
                $internationalCost = $internationalCost > $feeInternationalOfficialM3 ? $internationalCost : $feeInternationalOfficialM3;
            }
        }
        return $internationalCost;
    }

    public function getCostCustomerOffer($key, $province, $customerId)
    {
        $cost = 0;

        $keyOffer = '';
        switch ($key) {
            case ConfigConstant::SERVICE_PACKAGE_SHIPPING_WEIGHT:
                switch ($province) {
                    case 'hanoi':
                        $keyOffer = 'trans_weight_hn_fee';
                        break;
                    case 'hochiminh':
                        $keyOffer = 'trans_weight_hcm_fee';
                        break;
                    case 'haiphong':
                        $keyOffer = 'trans_weight_hp_fee';
                        break;
                }
                break;
            case ConfigConstant::SERVICE_PACKAGE_SHIPPING_VOLUME:
                switch ($province) {
                    case 'hanoi':
                        $keyOffer = 'trans_volumn_hn_fee';
                        break;
                    case 'hochiminh':
                        $keyOffer = 'trans_volumn_hcm_fee';
                        break;
                    case 'haiphong':
                        $keyOffer = 'trans_volumn_hp_fee';
                        break;
                }
                break;
        }

        if ($keyOffer) {
            $cost = $this->getCustomerOffer($customerId, $keyOffer);
        }

        return $cost;
    }

    public function getCostCustomerOfferCN($key, $province, $customerId)
    {
        $cost = 0;

        $keyOffer = '';
        switch ($key) {
            case ConfigConstant::SERVICE_PACKAGE_SHIPPING_WEIGHT:
                switch ($province) {
                    case 'hanoi':
                        $keyOffer = 'cn_trans_weight_hn_fee';
                        break;
                    case 'hochiminh':
                        $keyOffer = 'cn_trans_weight_hcm_fee';
                        break;
                    case 'haiphong':
                        $keyOffer = 'cn_trans_weight_hp_fee';
                        break;
                }
                break;
            case ConfigConstant::SERVICE_PACKAGE_SHIPPING_VOLUME:
                switch ($province) {
                    case 'hanoi':
                        $keyOffer = 'cn_trans_volumn_hn_fee';
                        break;
                    case 'hochiminh':
                        $keyOffer = 'cn_trans_volumn_hcm_fee';
                        break;
                    case 'haiphong':
                        $keyOffer = 'cn_trans_volumn_hp_fee';
                        break;
                }
                break;
        }

        if ($keyOffer) {
            $cost = $this->getCustomerOffer($customerId, $keyOffer);
        }

        return $cost;
    }

    public function getCustomerOffer($customerId, $keyOffer) {
        $cost = 0;
        if (!$customerId) {
            return $cost;
        }

        $customer = Customer::query()->with('offer')->find($customerId);
        if (!$customer) {
            return $cost;
        }

        $cost = optional($customer->offer)->{$keyOffer} ?? 0;

        return floatval($cost);
    }

    /**
     * @param  float  $totalOrder
     * @param  float|null  $orderPercent
     * @param  string|null  $orderPercent
     * @return float
     */
    public function getOrderFee(float $totalOrder, ?string $customerId = null, ?float &$orderPercent = 0, string $type = ''): float
    {
        /* Phần type define như sau:
            isChangeOrder: Đây là type đơn được fix cứng %
            isMultipleSupplier: Đơn có nhiều ncc, hoặc được tách ra từ nhiều ncc
            Còn lại là sẽ tính theo ưu đãi trong user và theo config
        */
        $percent = $this->_configService->getResultFromValueByBetweenMinMax(
            ConfigConstant::SERVICE_ORDER_FEE,
            $totalOrder
        );
        if($type == 'isMultipleSupplier') {
            $percent = $orderPercent;
        }
        $default = json_decode($this->_configService->getValueByKey(ConfigConstant::SERVICE_ORDER_FEE), true)[0]['default'];
        // Percent mà > 100 thì nó sẽ là giá trị tối thiểu đặt hàng 9000/đơn
        $customerOffer = $this->getCustomerOffer($customerId, 'offer_rate');
        $percent       = $customerOffer ? $customerOffer : $percent;
        if($type == 'isChangeOrder') {
            $percent = $orderPercent;
        }
        $orderPercent  = $percent > 100 ? OrderConstant::ORDER_PERCENT_DEFAULT : $percent;

        if ($percent > 100) {
            $orderFee = $default;
        } else {
            $orderFee = ($type == 'isMultipleSupplier' || $type == 'isChangeOrder')  ?
                ($totalOrder * ($percent / 100))
                : max(($totalOrder * ($percent / 100)), $default);
        }

        return AccountingHelper::getCosts($orderFee);
    }

    /**
     * @param  float  $totalOrder
     * @param  string|null  $customerId
     * @param  float|null  $orderPercent
     * @return float
     */
    public function getDepositCost(float $totalOrder, ?string $customerId = null, ?float &$orderPercent = 0): float
    {
        $percent = $this->_configService->getResultFromValueByBetweenMinMax(
            ConfigConstant::SERVICE_ORDER_FEE,
            $totalOrder
        );
        $default = json_decode($this->_configService->getValueByKey(ConfigConstant::SERVICE_ORDER_FEE), true)[0]['default'];
        // Percent mà > 100 thì nó sẽ là giá trị tối thiểu đặt hàng 9000/đơn
        $customerOffer = $this->getCustomerOffer($customerId, 'deposit_rate');
        $percent       = $customerOffer ? $customerOffer : $percent;
        $orderPercent  = $percent > 100 ? 3 : $percent;

        return AccountingHelper::getCosts($percent > 100  ? $default : max(($totalOrder * ($percent / 100)), $default));
    }

    /**
     * @param  float  $totalOrder
     * @param  int  $level
     * @return float
     */
    public function getCustomerLevelCost(float $totalOrder, int $level): float
    {
        return AccountingHelper::getCosts(
            $totalOrder * $this->getPercent($this->_configService->getResultFromValueByLevel($level)->order)
        );
    }

    /**
     * @param  float  $orderCost
     * @return float
     */
    public function getInsuranceCost(float $orderCost): float
    {
        return AccountingHelper::getCosts(
            $orderCost * $this->getPercent(
                (float)$this->_configService->getValueByKey(ConfigConstant::SERVICE_INSURANCE)
            )
        );
    }

    /**
     * @param  float|null  $number
     * @return float|int
     */
    public function getPercent(?float $number): float
    {
        return ($number ?? 0) / 100;
    }

    /**
     * @param $item
     * @param  float  $value
     * @return float
     */
    private function getCostByFirstSubsequent($item, float $value): float
    {
        if (!$item) {
            return 0;
        }
        if ($value > 1) {
            return ((float)$item->first + (float)$item->subsequent * ($value - 1));
        }
        return (float)$item->first * $value;
    }

    /**
     * @param  array  $products
     * @param  float  $exchangeRate
     * @return float
     */
    private function getTotalOrderInProducts(array $products, float $exchangeRate): float
    {
        $results = 0;
        foreach ($products as $product) {
            $results += $product['quantity'] * $product['unit_price_cny'];
        }
        return AccountingHelper::getCosts($results * $exchangeRate);
    }

    /**
     * @param  float  $orderCost
     * @param string $customerId
     * @return float
     */
    public function getDiscountCost(float $orderCost, string $customerId = null): float
    {
        $level = 1;

        if ($customerId) {
            $customer = Customer::find($customerId);
            $level    = $customer ? $customer->level : 1;
        }

        return $this->getCustomerLevelCost($orderCost, $level);
    }

    /**
     * @param  float  $weight
     * @return float
     */
    public function getShockCost(float $weight, $package = null): float
    {
        $weight = roundHalfUp($weight);
        // Nếu có config ở lúc tạo đơn thì lấy theo config luôn, không có thì lấy ở bảng config
        if(isset($package) && $package->configs) {
            $data = json_decode($package->configs);
            $shockJson = $data->shock_proof[0];
        } else {
            $shockJson = json_decode($this->_configService->getValueByKey(ConfigConstant::SERVICE_SHOCK_PROOF));
        }
        return $weight == 0 ? 0 : $this->getCostByFirstSubsequent($shockJson, $weight);
    }

    /**
     * @param  int|null  $customerOffer
     * @return float
     */
    public function getDepositPercent($totalOrder, ?int $customerOffer = 0): float
    {
        $percent = $this->_configService->getResultFromValueByBetweenMinMax(
            ConfigConstant::SERVICE_ORDER_FEE,
            $totalOrder
        );

        // Percent mà > 100 thì nó sẽ là giá trị tối thiểu đặt hàng 9000/đơn
        //$customerOffer = $this->getCustomerOffer($customerId, 'deposit_rate');
        $percent       = $customerOffer > 0 ? $customerOffer : $percent;

        return $percent > 100 ? 3 : $percent;
    }
}
