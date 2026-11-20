<?php

namespace App\Services;
use App\Constants\Report\ChatBiz;
use App\Helpers\OrderStatus;
use App\Models\Customer;
use App\Models\Label;
use App\Models\Order;
use App\Models\CustomerOrderSale;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Reports\ReportCustomerOrderSaleStart;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class ReportCustomerOrderSale
{
    const GOOGLE_ADS = 'google_ads';
    const GOOGLE = 'google';
    const FACEBOOK_ADS = 'facebook_ads';
    const PERSONAL = 'personal';
    const BUSINESS = 'business';
    const CHAT_BIZ_URI = 'https://api-v2.chatbiz.io/get-data-customer-21328jd2138321h8213n32j1'; // count total
    const CHAT_BIZ_URI_ALL = 'https://api-v2.chatbiz.io/get-data-customer-927sj217j1239x129i388a'; //

    const CONSIGNMENT = 1;
    const ORDER = 0;
    protected ReportService $service;
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    private function getGroupId(): array
    {
        $results = UserGroup::query()->select('group_id')->pluck('group_id')->toArray();
        return array_unique($results);
    }

    private function getUserGroup($group_id = false): array
    {
        $query = User::query();
        $query = $query->whereExists(function ($query) use ($group_id){
            $query->select("users_group.id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
            if ($group_id) {
                $query->where('users_group.group_id', $group_id);
            }
        })
            ->where('status', 1)
            ->select('id');
        return $query->pluck('id')
            ->toArray();
    }

    private function getLabel($arrayCode): array
    {
        return Label::query()
            ->select('id')
            ->whereIn('code', $arrayCode)
            ->pluck('id')
            ->toArray();
    }

    private function processCustomerConsultingConsignment(
        $start_date,
        $end_date,
        $user_group_ids,
        $type,
        $groupMapping
    )
    {
        $label_ids = $this->getLabel(['KI_GUI', 'KI_GUI_DA_LEN_DON']);
        $customerConsultingConsignments = $this->customerConsultingConsignment(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids
        );
        $customerConsultingConsignmentWithLabel = $this->customerConsultingConsignmentWithLabel(
            $user_group_ids,
            $label_ids
        );
        $customerConsultingConsignmentWithCustomerLabel = $this->customerConsultingConsignmentWithCustomerLabel(
            $start_date,
            $end_date,
            $user_group_ids,
            $label_ids,
            $type
        );
        foreach ($customerConsultingConsignments as $consultingConsignment) {
            if (isset($groupMapping[$consultingConsignment->group_id])) {
                $groupMapping[$consultingConsignment->group_id][''];
            }
        }
        return $groupMapping;
    }

    private function customerConsultingConsignment($start_date, $end_date, $user_group_ids, $label_ids)
    {
        $flagDate = '2024-12-06 11:06:59';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if (strtotime($start_date) < strtotime($flagDate)) {
                $start_date = $flagDate;
            }
        }

        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->whereIn('customer_label.label_id', $label_ids)
            ->whereBetween('customer_label.created_at',[$start_date, $end_date])
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function customerConsultingConsignmentWithLabel($user_group_ids, $label_ids)
    {
        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'users_group.group_id'
        )
            ->join('users_group','users_group.user_id', 'customers.staff_group_id')
            ->whereIn('customers.label_id', $label_ids)
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('users_group.group_id');
        return $query->get();
    }

    private function customerConsultingConsignmentWithCustomerLabel(
        $start_date,
        $end_date,
        $user_group_ids,
        $label_ids,
        $type
    )
    {
        $flagDate = '2024-12-06 11:06:59';
        $month = date('m', strtotime($start_date));
        $year = date('Y', strtotime($start_date));
        if ($month < 12 && $year <= '2024') {

        } else {
            if ($type == 'month') {
                if (strtotime($start_date) < strtotime($flagDate)) {
                    $start_date = $flagDate;
                }
            }
        }

        $query = Customer::query()->select
        (
            DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
            'customers.staff_group_id'
        )
            ->join('customer_label','customer_label.customer_id','=','customers.id')
            ->where('customer_label.created_at', '<', $start_date)
            ->whereIn('customer_label.label_id', $label_ids)
            ->where('customers.status', 1)
            ->whereIn('customers.staff_group_id', $user_group_ids)
            ->groupBy('customers.staff_group_id');
        return $query->get();
    }

    private function processData(
        $start_date,
        $end_date,
        $list,
        $type
    ){
        $groupMapping = $this->mappingGroup($list, $type);
        $user_group_ids = $this->getUserGroup();
        $data = $this->processCustomerConsultingConsignment(
            $start_date,
            $end_date,
            $user_group_ids,
            $type,
            $groupMapping
        );
        return $data;
    }

    private function mappingGroup($list, $type): array
    {
        $groups = $this->getGroupId();
        $arrayData = [];
        foreach ($groups as $group) {
            $arrayData[$group] = [
                'user_group_id' => $group,
                'total_customer_consignment_advise' => 0,
                'total_customer_order_advise' => 0,
                'total_customer_consignment' => 0,
                'total_customer_order_first' => 0,
                'month' => $list->month,
                'year' => $list->year,
                'type' => $type,
                'week' => $type == 'week' ? $list->week : 0,
            ];
        }
        return $arrayData;
    }

    private function calculatorCustomerChatBiz($chatBizData): int
    {
        $customerPhone = [];
        foreach ($chatBizData['data'] as $data) {
            if (!empty($data['labels'])) {
                foreach ($data['labels'] as $label) {
                    if (in_array($label['id'], [
                        ChatBiz::CHAT_BIZ_KH_TU_VAN_KY_GUI, ChatBiz::CHAT_BIZ_KH_THANH_TOAN_HO
                    ]) && !in_array($data['phone_number'], $customerPhone)) {
                        $customerPhone[] = $data['phone_number'];
                    }
                }
            }
        }
        return sizeof(array_filter($customerPhone));
    }
    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $groupMapping = $this->processData($weekStartDate, $weekEndDate, $list, 'week');
        $chatBiz = $this->getDataChatBiz($calculatorDatetime['week_start_date'], $calculatorDatetime['week_end_date']);
        $countChatBiz = 0;
        if ($chatBiz && $chatBiz['status_code'] == 200) {
            $countChatBiz = $this->calculatorCustomerChatBiz($chatBiz);
        }
        $getCustomerAdviseConsignment = $this->getCustomerAdviseConsignment($weekStartDate, $weekEndDate);
        $total_customer_consignment_advise = $getCustomerAdviseConsignment->total_customer_advise_consignment ?? 0;
        $total_customer_consignment_advise += $countChatBiz;
        $getCustomerAdviseOrder = $this->getCustomerAdviseOrder($weekStartDate, $weekEndDate);
        $getCustomerConsignment = $this->getCustomerConsignment($weekStartDate, $weekEndDate);
        extract($this->getCustomerOrder($weekStartDate, $weekEndDate), EXTR_PREFIX_SAME, "wddx");
        $import = [
            'total_customer_consignment_advise' => $total_customer_consignment_advise,
            'total_customer_order_advise' => $getCustomerAdviseOrder->total_customer_advise_order ?? 0,
            'total_customer_consignment' => $getCustomerConsignment->total_customer_consignment ?? 0,
            'total_customer_order_first' => $total_customer_order_first,
            'total_customer_order_first_gg_ads_personal' => $total_customer_order_first_gg_ads_personal,
            'total_customer_order_first_gg_ads_business' => $total_customer_order_first_gg_ads_business,
            'total_customer_order_first_fb_ads_personal' => $total_customer_order_first_fb_ads_personal,
            'total_customer_order_first_fb_ads_business' => $total_customer_order_first_fb_ads_business,
            'total_amount_first' => $total_amount_first,
            'total_amount_first_gg_ads_personal' => $total_amount_first_gg_ads_personal,
            'total_amount_first_gg_ads_business' => $total_amount_first_gg_ads_business,
            'total_amount_first_fb_ads_personal' => $total_amount_first_fb_ads_personal,
            'total_amount_first_fb_ads_business' => $total_amount_first_fb_ads_business,
            'total_service_charge_first' => $total_service_charge_first,
            'total_service_charge_first_gg_ads_personal' => $total_service_charge_first_gg_ads_personal,
            'total_service_charge_first_gg_ads_business' => $total_service_charge_first_gg_ads_business,
            'total_service_charge_first_fb_ads_personal' => $total_service_charge_first_fb_ads_personal,
            'total_service_charge_first_fb_ads_business' => $total_service_charge_first_fb_ads_business,
            'total_customer_order_second_gg_ads' => $total_customer_order_second_gg_ads,
            'total_customer_order_second_fb_ads' => $total_customer_order_second_fb_ads,
            'total_amount_second' => $total_amount_second,
            'total_service_charge_second' => $total_service_charge_second,
            'week' => $list->week,
            'month' => $list->month,
            'year' => $list->year,
            'type' => 'week',
            'created_at' => $weekStartDate,
            'updated_at' => $weekEndDate
        ];
        $this->insert($import);
        return true;
    }
    public function cronjobInsertDataMonth($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($request);
        $fistDayOfMonth = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $lastDayOfMonth = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';

        $chatBiz = $this->getDataChatBiz($calculatorDatetime->fistDayOfMonth, $calculatorDatetime->lastDayOfMonth);
        $countChatBiz = 0;
        if ($chatBiz && $chatBiz['status_code'] == 200) {
            $countChatBiz = $this->calculatorCustomerChatBiz($chatBiz);
        }
        $getCustomerAdviseConsignment = $this->getCustomerAdviseConsignment($fistDayOfMonth, $lastDayOfMonth);
        $total_customer_consignment_advise = $getCustomerAdviseConsignment->total_customer_advise_consignment ?? 0;
        $total_customer_consignment_advise += $countChatBiz;
        $getCustomerAdviseOrder = $this->getCustomerAdviseOrder($fistDayOfMonth, $lastDayOfMonth);
        $getCustomerConsignment = $this->getCustomerConsignment($fistDayOfMonth, $lastDayOfMonth);
        extract($this->getCustomerOrder($fistDayOfMonth, $lastDayOfMonth), EXTR_PREFIX_SAME, "wddx");
        $import = [
            'total_customer_consignment_advise' => $total_customer_consignment_advise,
            'total_customer_order_advise' => $getCustomerAdviseOrder->total_customer_advise_order ?? 0,
            'total_customer_consignment' => $getCustomerConsignment->total_customer_consignment ?? 0,
            'total_customer_order_first' => $total_customer_order_first,
            'total_customer_order_first_gg_ads_personal' => $total_customer_order_first_gg_ads_personal,
            'total_customer_order_first_gg_ads_business' => $total_customer_order_first_gg_ads_business,
            'total_customer_order_first_fb_ads_personal' => $total_customer_order_first_fb_ads_personal,
            'total_customer_order_first_fb_ads_business' => $total_customer_order_first_fb_ads_business,
            'total_amount_first' => $total_amount_first,
            'total_amount_first_gg_ads_personal' => $total_amount_first_gg_ads_personal,
            'total_amount_first_gg_ads_business' => $total_amount_first_gg_ads_business,
            'total_amount_first_fb_ads_personal' => $total_amount_first_fb_ads_personal,
            'total_amount_first_fb_ads_business' => $total_amount_first_fb_ads_business,
            'total_service_charge_first' => $total_service_charge_first,
            'total_service_charge_first_gg_ads_personal' => $total_service_charge_first_gg_ads_personal,
            'total_service_charge_first_gg_ads_business' => $total_service_charge_first_gg_ads_business,
            'total_service_charge_first_fb_ads_personal' => $total_service_charge_first_fb_ads_personal,
            'total_service_charge_first_fb_ads_business' => $total_service_charge_first_fb_ads_business,
            'total_customer_order_second_gg_ads' => $total_customer_order_second_gg_ads,
            'total_customer_order_second_fb_ads' => $total_customer_order_second_fb_ads,
            'total_amount_second' => $total_amount_second,
            'total_service_charge_second' => $total_service_charge_second,
            'week' => 0,
            'type' => 'month',
            'month' => $list->month,
            'year' => $list->year,
            'created_at' => $fistDayOfMonth,
            'updated_at' => $lastDayOfMonth
        ];
        $this->insert($import);
        return true;
    }

    public function getDataChatBiz($weekStartDate, $weekEndDate)
    {
        try {
            $data = [
                'date_start' => $weekStartDate,
                'date_end' => $weekEndDate
            ];
            $data_string = json_encode($data);
            $curl = ChatBiz::CHAT_BIZ_URI_ALL;
            $curl = curl_init($curl);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
            );
            $result = curl_exec($curl);
            curl_close($curl);
            Log::info('GET data from chatBiz ReportCustomerOrderSale '. $data_string);
            return json_decode($result,true);
        } catch (\Exception $e) {
            Log::info('GET data from chatBiz Error : '.$e->getMessage());
            return false;
        }
    }

    private function getCustomerAdviseConsignment($weekStartDate, $weekEndDate){
        return Customer::query()->select(
            DB::raw('COUNT(*) as total_customer_advise_consignment'),
        )
        ->where('customers.service',ReportCustomerOrderSale::CONSIGNMENT)
        ->whereNotnull('customers.staff_group_id')
        ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
        ->first();
    }

    private function getCustomerAdviseOrder($weekStartDate, $weekEndDate){
        return Customer::query()->select(
            DB::raw('COUNT(*) as total_customer_advise_order'),
        )
        ->where('customers.service',ReportCustomerOrderSale::ORDER)
        ->whereNotnull('customers.staff_group_id')
        ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
        ->first();
    }

    private function getCustomerConsignment($weekStartDate, $weekEndDate){
        return Customer::query()->select(
                DB::raw('COUNT(DISTINCT consignments.customer_id) as total_customer_consignment'),
            )
            ->join('consignments','consignments.customer_id','=','customers.id')
            ->whereBetween('consignments.created_at',[$weekStartDate, $weekEndDate])
            ->whereNull('consignments.deleted_at')
            ->first();
    }
    private function calculatorArrayCustomer($results): array
    {
        $arrayCustomerAll = [];
        $arrayCustomer = [];
        foreach ($results as $items) {
            $arrayCustomerAll[] = $items->toArray();
        }
        foreach ($arrayCustomerAll as $customerALl) {
            $customerALl['time'] = strtotime($customerALl['orderCreated']);
            $arrayCustomer[$customerALl['customerId']][] = $customerALl;
        }

        $arrayCustomerFirst = [];
        foreach ($arrayCustomer as $key => $value) {
            $arrayCustomerFirst[$key] = array_reduce($value, function($a, $b){
                return $a['time'] < $b['time'] ? $a : $b;
            }, array_shift($value));
        }
        $arrayCustomerSecond = [];
        foreach ($arrayCustomer as $key => $customer) {
            if (isset($arrayCustomerFirst[$key])) {
                foreach ($customer as $cus) {
                    if ($cus['time'] != $arrayCustomerFirst[$key]['time']) {
                        $arrayCustomerSecond[$key][] = $cus;
                    }
                }
            }
        }
        return [
            'arrayCustomerSecond' => $arrayCustomerSecond,
            'arrayCustomerFirst' => $arrayCustomerFirst
        ];
    }
    /*
        - khách hàng có đơn đầu tiên (trạng thái Đặt hàng) trong tuần báo cáo
        - ko quan tâm khách tạo tài khoản khi nào
    */
    private function getCustomerOrder($weekStartDate, $weekEndDate): array
    {
        $results = $this->getSqlDataCustomerOrder($weekStartDate, $weekEndDate);
        $calculatorArrayCustomer = $this->calculatorArrayCustomer($results);
        $arrayCustomerFirst = $calculatorArrayCustomer['arrayCustomerFirst'] ?? [];
        $arrayCustomerSecond = $calculatorArrayCustomer['arrayCustomerSecond'] ?? [];
        extract($this->calculatorSecond($arrayCustomerSecond), EXTR_PREFIX_SAME, "wddx");
        extract($this->calculatorFirst($arrayCustomerFirst), EXTR_PREFIX_SAME, "wddx");
        return [
            'total_customer_order_first' => $total_customer_order_first,
            'total_customer_order_first_gg_ads_personal' => $total_customer_order_first_gg_ads_personal,
            'total_customer_order_first_gg_ads_business' => $total_customer_order_first_gg_ads_business,
            'total_customer_order_first_fb_ads_personal' => $total_customer_order_first_fb_ads_personal,
            'total_customer_order_first_fb_ads_business' => $total_customer_order_first_fb_ads_business,
            'total_amount_first' => $total_amount_first,
            'total_amount_first_gg_ads_personal' => $total_amount_first_gg_ads_personal,
            'total_amount_first_gg_ads_business' => $total_amount_first_gg_ads_business,
            'total_amount_first_fb_ads_personal' => $total_amount_first_fb_ads_personal,
            'total_amount_first_fb_ads_business' => $total_amount_first_fb_ads_business,
            'total_service_charge_first' => $total_service_charge_first,
            'total_service_charge_first_gg_ads_personal' => $total_service_charge_first_gg_ads_personal,
            'total_service_charge_first_gg_ads_business' => $total_service_charge_first_gg_ads_business,
            'total_service_charge_first_fb_ads_personal' => $total_service_charge_first_fb_ads_personal,
            'total_service_charge_first_fb_ads_business' => $total_service_charge_first_fb_ads_business,
            'total_customer_order_second_gg_ads' => $total_customer_order_second_gg_ads,
            'total_customer_order_second_fb_ads' => $total_customer_order_second_fb_ads,
            'total_amount_second' => $total_amount_second,
            'total_service_charge_second' => $total_service_charge_second,
        ];
    }

    private function calculatorSecond($arrayCustomerSecond): array
    {
        $total_amount_second = 0;
        $total_service_charge_second = 0;
        $customerFb = [];
        $customerGg = [];
        foreach ($arrayCustomerSecond as $valSecond) {
            foreach ($valSecond as $valS) {
                $total_amount_second += $valS['amount'];
                $total_service_charge_second += $valS['service_charge'];
                if ($valS['social'] == ReportCustomerOrderSale::GOOGLE_ADS) {
                    if (!in_array($valS['customerId'], $customerGg)) {
                        $customerGg[] = $valS['customerId'];
                    }
                }
                if ($valS['social'] == ReportCustomerOrderSale::FACEBOOK_ADS) {
                    if (!in_array($valS['customerId'], $customerFb)) {
                        $customerFb[] = $valS['customerId'];
                    }
                }
            }
        }
        return [
            'total_customer_order_second_gg_ads' => count($customerGg),
            'total_customer_order_second_fb_ads' => count($customerFb),
            'total_amount_second' => $total_amount_second,
            'total_service_charge_second' => $total_service_charge_second,
        ];
    }

    private function calculatorFirst($arrayCustomerFirst): array
    {
        foreach ($arrayCustomerFirst as $value) {
            if ($value['social'] == ReportCustomerOrderSale::GOOGLE_ADS) {
                if ($value['business_type'] == ReportCustomerOrderSale::PERSONAL) {
                    $dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL][] = $value;
                }
                if ($value['business_type'] == ReportCustomerOrderSale::BUSINESS) {
                    $dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS][] = $value;
                }
            }
            if ($value['social'] == ReportCustomerOrderSale::GOOGLE) {
                if ($value['business_type'] == ReportCustomerOrderSale::PERSONAL) {
                    $dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL][] = $value;
                }
                if ($value['business_type'] == ReportCustomerOrderSale::BUSINESS) {
                    $dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS][] = $value;
                }
            }
            if ($value['social'] == ReportCustomerOrderSale::FACEBOOK_ADS) {
                if ($value['business_type'] == ReportCustomerOrderSale::PERSONAL) {
                    $dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL][] = $value;
                }
                if ($value['business_type'] == ReportCustomerOrderSale::BUSINESS) {
                    $dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS][] = $value;
                }
            }
        }
        /* Tổng giá trị đơn hàng đầu tiên*/
        $total_amount_first_gg_personal = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL]) ? collect($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL])->sum('amount') : 0;
        $total_amount_first_gg_business = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS]) ? collect($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS])->sum('amount') : 0;
        $total_amount_first_gg_ads_personal = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL]) ? collect($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL])->sum('amount') : 0;
        $total_amount_first_gg_ads_business = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS]) ? collect($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS])->sum('amount') : 0;
        $total_amount_first_fb_ads_personal = isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL]) ? collect($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL])->sum('amount') : 0;
        $total_amount_first_fb_ads_business = isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS]) ? collect($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS])->sum('amount') : 0;
        $total_amount_first_gg_ads_personal += $total_amount_first_gg_personal;
        $total_amount_first_gg_ads_business += $total_amount_first_gg_business;
        $total_amount_first = collect(
            [
                $total_amount_first_gg_ads_personal,
                $total_amount_first_gg_ads_business,
                $total_amount_first_fb_ads_personal,
                $total_amount_first_fb_ads_business
            ])->sum();
        /* Tổng phí dịch vụ đơn hàng đầu tiên*/
        $total_service_charge_first_gg_personal = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL]) ? collect($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL])->sum('service_charge') : 0;
        $total_service_charge_first_gg_business = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS]) ? collect($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS])->sum('service_charge') : 0;
        $total_service_charge_first_gg_ads_personal = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL]) ? collect($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL])->sum('service_charge') : 0;
        $total_service_charge_first_gg_ads_business = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS]) ? collect($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS])->sum('service_charge') : 0;
        $total_service_charge_first_fb_ads_personal = isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL]) ? collect($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL])->sum('service_charge') : 0;
        $total_service_charge_first_fb_ads_business = isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS]) ? collect($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS])->sum('service_charge') : 0;
        $total_service_charge_first_gg_ads_personal += $total_service_charge_first_gg_personal;
        $total_service_charge_first_gg_ads_business += $total_service_charge_first_gg_business;
        $total_service_charge_first = collect(
            [
                $total_service_charge_first_gg_ads_personal,
                $total_service_charge_first_gg_ads_business,
                $total_service_charge_first_fb_ads_personal,
                $total_service_charge_first_fb_ads_business
            ])->sum();
            $total_customer_order_first_gg_ads_personal = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL]) ? sizeof($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::PERSONAL]) : 0;
            $total_customer_order_first_gg_ads_business = isset($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS]) ? sizeof($dataGoogleAds[ReportCustomerOrderSale::GOOGLE_ADS][ReportCustomerOrderSale::BUSINESS]) : 0;
            $total_customer_order_first_gg_personal = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL]) ? sizeof($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::PERSONAL]) : 0;
            $total_customer_order_first_gg_business = isset($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS]) ? sizeof($dataGoogle[ReportCustomerOrderSale::GOOGLE][ReportCustomerOrderSale::BUSINESS]) : 0;
        return [
            'total_customer_order_first' => sizeof($arrayCustomerFirst) ?? 0,
            'total_customer_order_first_gg_ads_personal' => $total_customer_order_first_gg_ads_personal + $total_customer_order_first_gg_personal,
            'total_customer_order_first_gg_ads_business' => $total_customer_order_first_gg_ads_business + $total_customer_order_first_gg_business,
            'total_customer_order_first_fb_ads_personal' => isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL]) ? sizeof($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::PERSONAL]) : 0,
            'total_customer_order_first_fb_ads_business' => isset($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS]) ? sizeof($dataFacebook[ReportCustomerOrderSale::FACEBOOK_ADS][ReportCustomerOrderSale::BUSINESS]) : 0,
            'total_amount_first' => $total_amount_first,
            'total_amount_first_gg_ads_personal' => $total_amount_first_gg_ads_personal,
            'total_amount_first_gg_ads_business' => $total_amount_first_gg_ads_business,
            'total_amount_first_fb_ads_personal' => $total_amount_first_fb_ads_personal,
            'total_amount_first_fb_ads_business' => $total_amount_first_fb_ads_business,
            'total_service_charge_first' => $total_service_charge_first,
            'total_service_charge_first_gg_ads_personal' => $total_service_charge_first_gg_ads_personal,
            'total_service_charge_first_gg_ads_business' => $total_service_charge_first_gg_ads_business,
            'total_service_charge_first_fb_ads_personal' => $total_service_charge_first_fb_ads_personal,
            'total_service_charge_first_fb_ads_business' => $total_service_charge_first_fb_ads_business,
        ];
    }

    private function getSqlDataCustomerOrder($weekStartDate, $weekEndDate): LazyCollection
    {
        $sql = Order::query()->select
        (
            'customers.name as customerName',
            'customers.id as customerId',
            'orders.date_ordered as orderCreated',
            'orders.id as orderId',
            'customers.via as social',
            'customers.business_type',
            DB::raw('(orders.order_cost + orders.inspection_cost
                                                + orders.international_shipping_cost
                                                + orders.order_fee
                                                + (orders.china_shipping_cost * orders.exchange_rate)
                                                - orders.discount_cost
                                                ) as amount'
            ),
            DB::raw('(orders.order_fee) as service_charge')
        )
        ->join('customers','orders.customer_id','=','customers.id')
        ->where('orders.status',OrderStatus::DAT_HANG)
        ->whereNotNull('customers.via')
        ->whereNotNull('customers.business_type')
        ->whereIn('customers.via',[
            ReportCustomerOrderSale::GOOGLE_ADS,
            ReportCustomerOrderSale::FACEBOOK_ADS,
            ReportCustomerOrderSale::GOOGLE
        ])
        ->whereIn('customers.business_type',[ReportCustomerOrderSale::PERSONAL, ReportCustomerOrderSale::BUSINESS])
        ->whereNull('orders.deleted_at')
        ->whereBetween('orders.date_ordered',[$weekStartDate, $weekEndDate])
        ->orderBy('orders.date_ordered', 'ASC')
        ->cursor();
        return $sql;
    }

    public function insert($data){
        try {
            CustomerOrderSale::query()->insert($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return CustomerOrderSale::query()->where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->where('type','week')
            ->delete();
    }

    public function deleteMonth($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return CustomerOrderSale::query()->where('year', $list->year)
            ->where('type','month')
            ->where('month', $list->month)
            ->delete();
    }

    public function getReport($params): array
    {
        $weekEnd = $params['weekEnd'];
        $monthEnd = $params['monthEnd'];
        $yearEnd = $params['yearEnd'];
        $weekStart = $params['weekStart'];
        $monthStart = $params['monthStart'];
        $yearStart = $params['yearStart'];
        /* Lấy data tuần xem*/
        $query = CustomerOrderSale::query();
        $queryEnd = clone $query;
        if ($weekEnd) {
            $queryEnd = $queryEnd->where('week', $weekEnd);
        } else {
            $queryEnd = $queryEnd->where('week', 0);
        }
        if ($monthEnd) {
            $queryEnd = $queryEnd->where('month', $monthEnd);
        }
        if ($yearEnd) {
            $queryEnd = $queryEnd->where('year', $yearEnd);
        }
        $resultEnd = $queryEnd->get();
        /* Lấy data tuần tham chiếu*/
        $queryStart = clone $query;
        if ($weekStart) {
            $queryStart = $queryStart->where('week', $weekStart);
        } else {
            $queryStart = $queryStart->where('week', 0);
        }
        if ($monthStart) {
            $queryStart = $queryStart->where('month', $monthStart);
        }
        if ($yearStart) {
            $queryStart = $queryStart->where('year', $yearStart);
        }
        $resultStart = $queryStart->get();
        if (!empty($weekStart)) {
            $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearStart, $monthStart, $weekStart);
            $startDateStartOfWeek = date('d-m-Y',strtotime($res['week_start_date']));
            $endDateStartOfWeek = date('d-m-Y',strtotime($res['week_end_date']));
        } else {
            $res = CalculatorWeekReport::getStartAndEndDateOfMonth(['year' => $yearStart, 'month' => $monthStart]);
            $startDateStartOfWeek = date('d-m-Y',strtotime($res->fistDayOfMonth));
            $endDateStartOfWeek = date('d-m-Y',strtotime($res->lastDayOfMonth));
        }
        if (!empty($weekEnd)) {
            $res = CalculatorWeekReport::getDayOfWeekOfMonth($yearEnd, $monthEnd, $weekEnd);
            $startDateEndOfWeek = date('d-m-Y',strtotime($res['week_start_date']));
            $endDateEndOfWeek = date('d-m-Y',strtotime($res['week_end_date']));
        } else {
            $res = CalculatorWeekReport::getStartAndEndDateOfMonth(['year' => $yearEnd, 'month' => $monthEnd]);
            $startDateEndOfWeek = date('d-m-Y',strtotime($res->fistDayOfMonth));
            $endDateEndOfWeek = date('d-m-Y',strtotime($res->lastDayOfMonth));
        }
        $reportStart = (new ReportCustomerOrderSaleStart($resultStart))
            ->getCustomerConsignmentAdvise()
            ->getCustomerOrderAdvise()
            ->customerConsignment()
            ->customerOrderFirst()
            ->customerOrderFirstGooglePersonal()
            ->customerOrderFirstGoogleBusiness()
            ->customerOrderFirstFacebookPersonal()
            ->customerOrderFirstFacebookBusiness()
            ->amountFirst()
            ->amountFirstGooglePersonal()
            ->amountFirstGoogleBusiness()
            ->amountFirstFacebookPersonal()
            ->amountFirstFacebookBusiness()
            ->serviceChargeFirst()
            ->serviceChargeFirstGooglePersonal()
            ->serviceChargeFirstGoogleBusiness()
            ->serviceChargeFirstFacebookPersonal()
            ->serviceChargeFirstFacebookBusiness()
            ->customerOrderSecondGoogle()
            ->customerOrderSecondFacebook()
            ->amountSecond()
            ->serviceChargeSecond()
            ->exec();
        $reportEnd = (new ReportCustomerOrderSaleStart($resultEnd))
            ->getCustomerConsignmentAdvise()
            ->getCustomerOrderAdvise()
            ->customerConsignment()
            ->customerOrderFirst()
            ->customerOrderFirstGooglePersonal()
            ->customerOrderFirstGoogleBusiness()
            ->customerOrderFirstFacebookPersonal()
            ->customerOrderFirstFacebookBusiness()
            ->amountFirst()
            ->amountFirstGooglePersonal()
            ->amountFirstGoogleBusiness()
            ->amountFirstFacebookPersonal()
            ->amountFirstFacebookBusiness()
            ->serviceChargeFirst()
            ->serviceChargeFirstGooglePersonal()
            ->serviceChargeFirstGoogleBusiness()
            ->serviceChargeFirstFacebookPersonal()
            ->serviceChargeFirstFacebookBusiness()
            ->customerOrderSecondGoogle()
            ->customerOrderSecondFacebook()
            ->amountSecond()
            ->serviceChargeSecond()
            ->exec();
        $isDisplayWeekEnd =  $weekEnd ? ' Tuần '.$weekEnd : '';
        $isDisplayWeekStart =  $weekStart ? ' Tham chiếu tuần '.$weekStart : '';
        $oav_data_two = $reportStart->total_customer_order_first > 0
            ? $reportStart->total_amount_first / $reportStart->total_customer_order_first
            : 0;
        $oav_data_three = $reportEnd->total_customer_order_first > 0
            ? $reportEnd->total_amount_first / $reportEnd->total_customer_order_first
            : 0;
        $data['format'] = [
            [
                'data_one' => '',
                'data_two' => $isDisplayWeekStart.' tháng '.$monthStart.'/'.$yearStart.' ( '.$startDateStartOfWeek .' đến '.$endDateStartOfWeek.' )',
                'data_three' => $isDisplayWeekEnd.' tháng '.$monthEnd.'/'.$yearEnd.' ( '.$startDateEndOfWeek .' đến '.$endDateEndOfWeek.' )',
                'data_four' => '% Tăng trưởng',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH tư vấn ký gửi',
                'data_two' => number_format($reportStart->total_customer_consignment_advise, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_consignment_advise, 0, ',','.'),
                'data_four' => $reportStart->total_customer_consignment_advise > 0 ?
                    round(
                        ($reportEnd->total_customer_consignment_advise - $reportStart->total_customer_consignment_advise)*100/$reportStart->total_customer_consignment_advise
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH Tư vấn order',
                'data_two' => number_format($reportStart->total_customer_order_advise, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_advise, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_advise > 0 ?
                    round(
                        ($reportEnd->total_customer_order_advise - $reportStart->total_customer_order_advise)*100/$reportStart->total_customer_order_advise
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH mới ký gửi',
                'data_two' => number_format($reportStart->total_customer_consignment, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_consignment, 0, ',','.'),
                'data_four' => $reportStart->total_customer_consignment > 0 ?
                    round(
                        ($reportEnd->total_customer_consignment - $reportStart->total_customer_consignment)*100/$reportStart->total_customer_consignment
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH mới đặt hàng',
                'data_two' => number_format($reportStart->total_customer_order_first, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_first, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_first > 0 ?
                    round(
                        ($reportEnd->total_customer_order_first - $reportStart->total_customer_order_first)*100/$reportStart->total_customer_order_first
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH tiêu dùng mới đặt hàng từ GG Ads',
                'data_two' => number_format($reportStart->total_customer_order_first_google_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_first_google_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_first_google_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_customer_order_first_google_ads_personal - $reportStart->total_customer_order_first_google_ads_personal)*100/$reportStart->total_customer_order_first_google_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH kinh doanh mới đặt hàng từ GG Ads',
                'data_two' => number_format($reportStart->total_customer_order_first_google_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_first_google_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_first_google_ads_business > 0 ?
                    round(
                        ($reportEnd->total_customer_order_first_google_ads_business - $reportStart->total_customer_order_first_google_ads_business)*100/$reportStart->total_customer_order_first_google_ads_business
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH tiêu dùng mới đặt hàng từ FB Ads',
                'data_two' => number_format($reportStart->total_customer_order_first_facebook_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_first_facebook_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_first_facebook_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_customer_order_first_facebook_ads_personal - $reportStart->total_customer_order_first_facebook_ads_personal)*100/$reportStart->total_customer_order_first_facebook_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'KH kinh doanh mới đặt hàng từ FB Ads',
                'data_two' => number_format($reportStart->total_customer_order_first_facebook_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_first_facebook_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_first_facebook_ads_business > 0 ?
                    round(
                        ($reportEnd->total_customer_order_first_facebook_ads_business - $reportStart->total_customer_order_first_facebook_ads_business)*100/$reportStart->total_customer_order_first_facebook_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới',
                'data_two' => number_format($reportStart->total_amount_first, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_first, 0, ',','.'),
                'data_four' => $reportStart->total_amount_first > 0 ?
                    round(
                        ($reportEnd->total_amount_first - $reportStart->total_amount_first)*100/$reportStart->total_amount_first
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ GG Ads tiêu dùng',
                'data_two' => number_format($reportStart->total_amount_first_google_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_first_google_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_amount_first_google_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_amount_first_google_ads_personal - $reportStart->total_amount_first_google_ads_personal)*100/$reportStart->total_amount_first_google_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ GG Ads kinh doanh',
                'data_two' => number_format($reportStart->total_amount_first_google_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_first_google_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_amount_first_google_ads_business > 0 ?
                    round(
                        ($reportEnd->total_amount_first_google_ads_business - $reportStart->total_amount_first_google_ads_business)*100/$reportStart->total_amount_first_google_ads_business
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ FB Ads tiêu dùng',
                'data_two' => number_format($reportStart->total_amount_first_facebook_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_first_facebook_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_amount_first_facebook_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_amount_first_facebook_ads_personal - $reportStart->total_amount_first_facebook_ads_personal)*100/$reportStart->total_amount_first_facebook_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Giá trị đơn hàng mới từ FB Ads kinh doanh',
                'data_two' => number_format($reportStart->total_amount_first_facebook_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_first_facebook_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_amount_first_facebook_ads_business > 0 ?
                    round(
                        ($reportEnd->total_amount_first_facebook_ads_business - $reportStart->total_amount_first_facebook_ads_business)*100/$reportStart->total_amount_first_facebook_ads_business
                    ).'%' : '0%',
                'data_five' => ''
            ],
            /*Service_change*/
            [
                'data_one' => 'Phí dịch vụ mới',
                'data_two' => number_format($reportStart->total_service_charge_first, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_first, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_first > 0 ?
                    round(
                        ($reportEnd->total_service_charge_first - $reportStart->total_service_charge_first)*100/$reportStart->total_service_charge_first
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ GG Ads tiêu dùng',
                'data_two' => number_format($reportStart->total_service_charge_first_google_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_first_google_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_first_google_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_service_charge_first_google_ads_personal - $reportStart->total_service_charge_first_google_ads_personal)*100/$reportStart->total_service_charge_first_google_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ GG Ads kinh doanh',
                'data_two' => number_format($reportStart->total_service_charge_first_google_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_first_google_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_first_google_ads_business > 0 ?
                    round(
                        ($reportEnd->total_service_charge_first_google_ads_business - $reportStart->total_service_charge_first_google_ads_business)*100/$reportStart->total_service_charge_first_google_ads_business
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ FB Ads tiêu dùng',
                'data_two' => number_format($reportStart->total_service_charge_first_facebook_ads_personal, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_first_facebook_ads_personal, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_first_facebook_ads_personal > 0 ?
                    round(
                        ($reportEnd->total_service_charge_first_facebook_ads_personal - $reportStart->total_service_charge_first_facebook_ads_personal)*100/$reportStart->total_service_charge_first_facebook_ads_personal
                    ).'%' : '0%',
                'data_five' => ''
            ],
            [
                'data_one' => 'Phí dịch vụ mới từ FB Ads kinh doanh',
                'data_two' => number_format($reportStart->total_service_charge_first_facebook_ads_business, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_first_facebook_ads_business, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_first_facebook_ads_business > 0 ?
                    round(
                        ($reportEnd->total_service_charge_first_facebook_ads_business - $reportStart->total_service_charge_first_facebook_ads_business)*100/$reportStart->total_service_charge_first_facebook_ads_business
                    ).'%' : '0%',
                'data_five' => ''
            ],
            /*Khach dat don thu 2 tro di*/
            [
                'data_one' => 'KH GG đặt từ đơn thứ 2 trở lên',
                'data_two' => number_format($reportStart->total_customer_order_second_google_ads, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_second_google_ads, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_second_google_ads > 0 ?
                    round(
                        ($reportEnd->total_customer_order_second_google_ads - $reportStart->total_customer_order_second_google_ads)*100/$reportStart->total_customer_order_second_google_ads
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'KH FB đặt từ đơn thứ 2 trở lên',
                'data_two' => number_format($reportStart->total_customer_order_second_facebook_ads, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_customer_order_second_facebook_ads, 0, ',','.'),
                'data_four' => $reportStart->total_customer_order_second_facebook_ads > 0 ?
                    round(
                        ($reportEnd->total_customer_order_second_facebook_ads - $reportStart->total_customer_order_second_facebook_ads)*100/$reportStart->total_customer_order_second_facebook_ads
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            /*Gia trị đơn thứ 2 trở đi*/
            [
                'data_one' => 'Giá trị đơn từ đơn thứ 2 trở lên',
                'data_two' => number_format($reportStart->total_amount_second, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_amount_second, 0, ',','.'),
                'data_four' => $reportStart->total_amount_second > 0 ?
                    round(
                        ($reportEnd->total_amount_second - $reportStart->total_amount_second)*100/$reportStart->total_amount_second
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            /*Phi dich vu don thu 2 tro di*/
            [
                'data_one' => 'Phí dịch vụ đơn từ đơn thứ 2 trở lên',
                'data_two' => number_format($reportStart->total_service_charge_second, 0, ',', '.'),
                'data_three' => number_format($reportEnd->total_service_charge_second, 0, ',','.'),
                'data_four' => $reportStart->total_service_charge_second > 0 ?
                    round(
                        ($reportEnd->total_service_charge_second - $reportStart->total_service_charge_second)*100/$reportStart->total_service_charge_second
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ],
            /*Tỷ lệ chốt*/
            [
                'data_one' => 'Tỉ lệ chốt (%)',
                'data_two' => ($reportStart->total_customer_order_advise == 0 ? 0 : round(($reportStart->total_customer_order_first * 100)/$reportStart->total_customer_order_advise, 2)).'%',
                'data_three' => ($reportEnd->total_customer_order_advise == 0 ? 0 : round(($reportEnd->total_customer_order_first * 100)/$reportEnd->total_customer_order_advise, 2)).'%',
                'data_four' => round((resultDevision($reportStart->total_customer_order_first, $reportStart->total_customer_order_advise) == 0 ? 0 : ((resultDevision($reportEnd->total_customer_order_first, $reportEnd->total_customer_order_advise) - resultDevision($reportStart->total_customer_order_first, $reportStart->total_customer_order_advise))*100/resultDevision($reportStart->total_customer_order_first, $reportStart->total_customer_order_advise))), 2).'%',
                'data_five' => 'isBool'
            ],
            /*Tỷ lệ chốt GG*/
            [
                'data_one' => 'Tỉ lệ chốt GG (%)',
                'data_two' => '0'.'%',
                'data_three' => '0'.'%',
                'data_four' => '0'.'%',
                'data_five' => 'isBool'
            ],
            /*Tỷ lệ chốt FB*/
            [
                'data_one' => 'Tỉ lệ chốt FB (%)',
                'data_two' => '0'.'%',
                'data_three' => '0'.'%',
                'data_four' => '0'.'%',
                'data_five' => 'isBool'
            ],
            [
                'data_one' => 'AOV',
                'data_two' => number_format($oav_data_two, 0, ',', '.'),
                'data_three' => number_format($oav_data_three, 0, ',', '.'),
                'data_four' =>  $oav_data_two > 0 ? round(
                        ($oav_data_two - $oav_data_three)*100/$oav_data_two
                    ).'%' : '0%',
                'data_five' => 'isBool'
            ]
        ];
        return $data;
    }

}
