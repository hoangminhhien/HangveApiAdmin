<?php

namespace App\Services;

use App\Constants\Report\ChatBiz;
use App\Helpers\OrderStatus;
use App\Models\CustomerOrderSaleStaff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ReportCustomerOrderSaleStaff
{
    protected ReportService $service;
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    static function acceptStaff(): array
    {
        return [
            '0366195571','0369109625',
            '0348423737','0941396576',
            '0335764510','0965437232',
            '0384085236','0833206389',
        ];
    }

    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $results = $this->getResult($weekStartDate, $weekEndDate);
        $arrayCustomerFirst = [];
        $arrayCustomerSecond = [];
        $arrayUserId = [];
        $arrayUser = [];
        foreach ($results as $items) {
            $itemArray = $items->toArray();
            $arrayCustomerFirst[$itemArray['customer_id']] = $itemArray;
            $arrayCustomerSecond[$itemArray['customer_id']][] = $itemArray;
            if (!in_array($itemArray['user_id'], $arrayUserId)) {
                $arrayUserId[] = $itemArray['user_id'];
                $arrayUser[$itemArray['user_id']]['name'] = $itemArray['user_name'];
            }
        }
        foreach ($arrayCustomerSecond as $keySecond => &$valueSecond) {
            foreach ($valueSecond as $key => $second) {
                if (
                    isset($arrayCustomerFirst[$keySecond])
                    && $second['order_id'] == $arrayCustomerFirst[$keySecond]['order_id']
                ) {
                    unset($valueSecond[$key]);
                }
            }
        }
        unset($valueSecond);
        $calculatorFirst = $this->calculatorFirst($arrayCustomerFirst, $arrayUserId);
        $calculatorSecond = $this->calculatorSecond($arrayCustomerSecond, $arrayUserId);
        $mergeData = $this->mergeData($calculatorFirst, $calculatorSecond, $arrayUserId);
        $users = $this->getUser();
        $dataImport = [];
        foreach ($users as $user) {
            $dataImport[$user['id']]['total_second_customer_amount'] = 0;
            $dataImport[$user['id']]['total_second_customer_service_change'] = 0;
            $dataImport[$user['id']]['total_second_customer_google'] = 0;
            $dataImport[$user['id']]['total_second_customer_facebook'] = 0;
            $dataImport[$user['id']]['total_amount_all'] = 0;
            $dataImport[$user['id']]['total_service_change_all'] = 0;
            $dataImport[$user['id']]['total_first_amount_google_personal'] = 0;
            $dataImport[$user['id']]['total_first_service_change_google_personal'] = 0;
            $dataImport[$user['id']]['total_first_customer_google_personal'] = 0;
            $dataImport[$user['id']]['total_first_amount_google_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_service_change_google_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_customer_google_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_amount_facebook_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_service_change_facebook_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_customer_facebook_ads_personal'] = 0;
            $dataImport[$user['id']]['total_first_amount_google_business'] = 0;
            $dataImport[$user['id']]['total_first_service_change_google_business'] = 0;
            $dataImport[$user['id']]['total_first_customer_google_business'] = 0;
            $dataImport[$user['id']]['total_first_amount_google_ads_business'] = 0;
            $dataImport[$user['id']]['total_first_service_change_google_ads_business'] = 0;
            $dataImport[$user['id']]['total_first_customer_google_ads_business'] = 0;
            $dataImport[$user['id']]['total_first_amount_facebook_ads_business'] = 0;
            $dataImport[$user['id']]['total_first_service_change_facebook_ads_business'] = 0;
            $dataImport[$user['id']]['total_first_customer_facebook_ads_business'] = 0;
            $dataImport[$user['id']]['user_id'] = $user['id'];
            $dataImport[$user['id']]['user_name'] = $user['user_name'];
            $dataImport[$user['id']]['week'] = $list->week;
            $dataImport[$user['id']]['month'] = $list->month;
            $dataImport[$user['id']]['year'] = $list->year;
            $dataImport[$user['id']]['created_at'] = $weekStartDate;
            $dataImport[$user['id']]['updated_at'] = $weekEndDate;
        }
        unset($user);
        if (!empty($mergeData)) {
            foreach ($users as $user) {
                foreach ($mergeData as $key => $value) {
                    if ($user['id'] == $key) {
                        $dataImport[$key] = $value;
                        $dataImport[$key]['user_id'] = $key;
                        $dataImport[$key]['user_name'] = isset($arrayUser[$key]) ? $arrayUser[$key]['name'] : '';
                        $dataImport[$key]['week'] = $list->week;
                        $dataImport[$key]['month'] = $list->month;
                        $dataImport[$key]['year'] = $list->year;
                        $dataImport[$key]['created_at'] = $weekStartDate;
                        $dataImport[$key]['updated_at'] = $weekEndDate;
                    }
                }
            }
        }
        $this->insert($dataImport);
        return true;
    }

    private function mergeData($calculatorFirst, $calculatorSecond, $arrayUser): array
    {

        $data_first_google = $calculatorFirst['data_first_google'];
        $data_first_google_ads = $calculatorFirst['data_first_google_ads'];
        $data_first_facebook_ads = $calculatorFirst['data_first_facebook_ads'];

        $data_second_google = $calculatorSecond['data_second_google'];
        $data_second_google_ads = $calculatorSecond['data_second_google_ads'];
        $data_second_facebook_ads = $calculatorSecond['data_second_facebook_ads'];

        foreach ($arrayUser as $user) {
            /* Personal */
            /* Second */;
            $data_user[$user]['total_second_amount_google_personal'] = 0;
            $data_user[$user]['total_second_service_change_google_personal'] = 0;
            $data_user[$user]['total_second_customer_google_personal'] = 0;

            $data_user[$user]['total_second_amount_google_ads_personal'] = 0;
            $data_user[$user]['total_second_service_change_google_ads_personal'] = 0;
            $data_user[$user]['total_second_customer_google_ads_personal'] = 0;

            $data_user[$user]['total_second_amount_facebook_ads_personal'] = 0;
            $data_user[$user]['total_second_service_change_facebook_ads_personal'] = 0;
            $data_user[$user]['total_second_customer_facebook_ads_personal'] = 0;
            /* Business */
            /* Second */
            $data_user[$user]['total_second_amount_google_business'] = 0;
            $data_user[$user]['total_second_service_change_google_business'] = 0;
            $data_user[$user]['total_second_customer_google_business'] = 0;

            $data_user[$user]['total_second_amount_google_ads_business'] = 0;
            $data_user[$user]['total_second_service_change_google_ads_business'] = 0;
            $data_user[$user]['total_second_customer_google_ads_business'] = 0;

            $data_user[$user]['total_second_amount_facebook_ads_business'] = 0;
            $data_user[$user]['total_second_service_change_facebook_ads_business'] = 0;
            $data_user[$user]['total_second_customer_facebook_ads_business'] = 0;

            /* First personal*/

            $data_user[$user]['total_first_amount_google_personal'] = 0;
            $data_user[$user]['total_first_service_change_google_personal'] = 0;
            $data_user[$user]['total_first_customer_google_personal'] = 0;

            $data_user[$user]['total_first_amount_google_ads_personal'] = 0;
            $data_user[$user]['total_first_service_change_google_ads_personal'] = 0;
            $data_user[$user]['total_first_customer_google_ads_personal'] = 0;

            $data_user[$user]['total_first_amount_facebook_ads_personal'] = 0;
            $data_user[$user]['total_first_service_change_facebook_ads_personal'] = 0;
            $data_user[$user]['total_first_customer_facebook_ads_personal'] = 0;

            /* First business*/
            $data_user[$user]['total_first_amount_google_business'] = 0;
            $data_user[$user]['total_first_service_change_google_business'] = 0;
            $data_user[$user]['total_first_customer_google_business'] = 0;

            $data_user[$user]['total_first_amount_google_ads_business'] = 0;
            $data_user[$user]['total_first_service_change_google_ads_business'] = 0;
            $data_user[$user]['total_first_customer_google_ads_business'] = 0;

            $data_user[$user]['total_first_amount_facebook_ads_business'] = 0;
            $data_user[$user]['total_first_service_change_facebook_ads_business'] = 0;
            $data_user[$user]['total_first_customer_facebook_ads_business'] = 0;

            /*Second google personal*/
            if (!empty($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL])) {
                $collect = collect($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL]);
                $total_second_amount_google_personal = $collect->sum('amount');
                $total_second_service_change_google_personal = $collect->sum('service_charge');
                $customer_second_google_personal_ids = [];
                foreach ($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_google_personal_ids)){
                        $customer_second_google_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_google_personal'] = $total_second_amount_google_personal;
                $data_user[$user]['total_second_service_change_google_personal'] = $total_second_service_change_google_personal;
                $data_user[$user]['total_second_customer_google_personal'] = sizeof($customer_second_google_personal_ids);
            }
            /*Second google ads personal*/
            if (!empty($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL])) {
                $collect = collect($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL]);
                $total_second_amount_google_ads_personal = $collect->sum('amount');
                $total_second_service_change_google_ads_personal = $collect->sum('service_charge');
                $customer_second_google_ads_personal_ids = [];
                foreach ($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_google_ads_personal_ids)){
                        $customer_second_google_ads_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_google_ads_personal'] = $total_second_amount_google_ads_personal;
                $data_user[$user]['total_second_service_change_google_ads_personal'] = $total_second_service_change_google_ads_personal;
                $data_user[$user]['total_second_customer_google_ads_personal'] = sizeof($customer_second_google_ads_personal_ids);
            }
            /*Second facebook personal*/
            if (!empty($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL])) {
                $collect = collect($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL]);
                $total_second_amount_facebook_ads_personal = $collect->sum('amount');
                $total_second_service_change_facebook_ads_personal = $collect->sum('service_charge');
                $customer_second_facebook_ads_personal_ids = [];
                foreach ($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_facebook_ads_personal_ids)){
                        $customer_second_facebook_ads_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_facebook_ads_personal'] = $total_second_amount_facebook_ads_personal;
                $data_user[$user]['total_second_service_change_facebook_ads_personal'] = $total_second_service_change_facebook_ads_personal;
                $data_user[$user]['total_second_customer_facebook_ads_personal'] = sizeof($customer_second_facebook_ads_personal_ids);
            }

            /*Second google business*/
            if (!empty($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS])) {
                $collect = collect($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS]);
                $total_second_amount_google_business = $collect->sum('amount');
                $total_second_service_change_google_business = $collect->sum('service_charge');
                $customer_second_google_business_ids = [];
                foreach ($data_second_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_google_business_ids)){
                        $customer_second_google_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_google_business'] = $total_second_amount_google_business;
                $data_user[$user]['total_second_service_change_google_business'] = $total_second_service_change_google_business;
                $data_user[$user]['total_second_customer_google_business'] = sizeof($customer_second_google_business_ids);
            }
            /*Second google ads business*/
            if (!empty($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS])) {
                $collect = collect($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS]);
                $total_second_amount_google_ads_business = $collect->sum('amount');
                $total_second_service_change_google_ads_business = $collect->sum('service_charge');
                $customer_second_google_ads_business_ids = [];
                foreach ($data_second_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_google_ads_business_ids)){
                        $customer_second_google_ads_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_google_ads_business'] = $total_second_amount_google_ads_business;
                $data_user[$user]['total_second_service_change_google_ads_business'] = $total_second_service_change_google_ads_business;
                $data_user[$user]['total_second_customer_google_ads_business'] = sizeof($customer_second_google_ads_business_ids);
            }
            /*Second facebook business*/
            if (!empty($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS])) {
                $collect = collect($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS]);
                $total_second_amount_facebook_ads_business = $collect->sum('amount');
                $total_second_service_change_facebook_ads_business = $collect->sum('service_charge');
                $customer_second_facebook_ads_business_ids = [];
                foreach ($data_second_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_second_facebook_ads_business_ids)){
                        $customer_second_facebook_ads_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_second_amount_facebook_ads_business'] = $total_second_amount_facebook_ads_business;
                $data_user[$user]['total_second_service_change_facebook_ads_business'] = $total_second_service_change_facebook_ads_business;
                $data_user[$user]['total_second_customer_facebook_ads_business'] = sizeof($customer_second_facebook_ads_business_ids);
            }

            /*First google personal*/
            if (!empty($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL])) {
                $collect = collect($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL]);
                $total_first_amount_google_personal = $collect->sum('amount');
                $total_first_service_change_google_personal = $collect->sum('service_charge');
                $customer_first_google_personal_ids = [];
                foreach ($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_google_personal_ids)){
                        $customer_first_google_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_google_personal'] = $total_first_amount_google_personal;
                $data_user[$user]['total_first_service_change_google_personal'] = $total_first_service_change_google_personal;
                $data_user[$user]['total_first_customer_google_personal'] = sizeof($customer_first_google_personal_ids);
            }

            /*First google_ads personal*/
            if (!empty($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL])) {
                $collect = collect($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL]);
                $total_first_amount_google_ads_personal = $collect->sum('amount');
                $total_first_service_change_google_ads_personal = $collect->sum('service_charge');
                $customer_first_google_ads_personal_ids = [];
                foreach ($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_google_ads_personal_ids)){
                        $customer_first_google_ads_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_google_ads_personal'] = $total_first_amount_google_ads_personal;
                $data_user[$user]['total_first_service_change_google_ads_personal'] = $total_first_service_change_google_ads_personal;
                $data_user[$user]['total_first_customer_google_ads_personal'] = sizeof($customer_first_google_ads_personal_ids);
            }

            /*First facebook_ads personal*/
            if (!empty($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL])) {
                $collect = collect($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL]);
                $total_first_amount_facebook_ads_personal = $collect->sum('amount');
                $total_first_service_change_facebook_ads_personal = $collect->sum('service_charge');
                $customer_first_facebook_ads_personal_ids = [];
                foreach ($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_facebook_ads_personal_ids)){
                        $customer_first_facebook_ads_personal_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_facebook_ads_personal'] = $total_first_amount_facebook_ads_personal;
                $data_user[$user]['total_first_service_change_facebook_ads_personal'] = $total_first_service_change_facebook_ads_personal;
                $data_user[$user]['total_first_customer_facebook_ads_personal'] = sizeof($customer_first_facebook_ads_personal_ids);
            }

            /*First google business*/
            if (!empty($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS])) {
                $collect = collect($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS]);
                $total_first_amount_google_business = $collect->sum('amount');
                $total_first_service_change_google_business = $collect->sum('service_charge');
                $customer_first_google_business_ids = [];
                foreach ($data_first_google[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_google_business_ids)){
                        $customer_first_google_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_google_business'] = $total_first_amount_google_business;
                $data_user[$user]['total_first_service_change_google_business'] = $total_first_service_change_google_business;
                $data_user[$user]['total_first_customer_google_business'] = sizeof($customer_first_google_business_ids);
            }

            /*First google_ads business*/
            if (!empty($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS])) {
                $collect = collect($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS]);
                $total_first_amount_google_ads_business = $collect->sum('amount');
                $total_first_service_change_google_ads_business = $collect->sum('service_charge');
                $customer_first_google_ads_business_ids = [];
                foreach ($data_first_google_ads[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_google_ads_business_ids)){
                        $customer_first_google_ads_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_google_ads_business'] = $total_first_amount_google_ads_business;
                $data_user[$user]['total_first_service_change_google_ads_business'] = $total_first_service_change_google_ads_business;
                $data_user[$user]['total_first_customer_google_ads_business'] = sizeof($customer_first_google_ads_business_ids);
            }

            /*First facebook_ads business*/
            if (!empty($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS])) {
                $collect = collect($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS]);
                $total_first_amount_facebook_ads_business = $collect->sum('amount');
                $total_first_service_change_facebook_ads_business = $collect->sum('service_charge');
                $customer_first_facebook_ads_business_ids = [];
                foreach ($data_first_facebook_ads[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS] as $count) {
                    if (!in_array($count['customer_id'], $customer_first_facebook_ads_business_ids)){
                        $customer_first_facebook_ads_business_ids[] = $count['customer_id'];
                    }
                }
                $data_user[$user]['total_first_amount_facebook_ads_business'] = $total_first_amount_facebook_ads_business;
                $data_user[$user]['total_first_service_change_facebook_ads_business'] = $total_first_service_change_facebook_ads_business;
                $data_user[$user]['total_first_customer_facebook_ads_business'] = sizeof($customer_first_facebook_ads_business_ids);
            }

            /* all fb + gg*/
            /* Tổng all giá trị từ đơn thứ ư*/
            $data[$user]['total_second_customer_amount'] = 0;
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_google_personal'];
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_google_ads_personal'];
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_google_business'];
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_google_ads_business'];
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_facebook_ads_personal'];
            $data[$user]['total_second_customer_amount'] += $data_user[$user]['total_second_amount_facebook_ads_business'];
            /*Tổng all phí dịch vụ từ đơn thứ 2*/
            $data[$user]['total_second_customer_service_change'] = 0;
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_google_personal'];
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_google_ads_personal'];
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_google_business'];
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_google_ads_business'];
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_facebook_ads_personal'];
            $data[$user]['total_second_customer_service_change'] += $data_user[$user]['total_second_service_change_facebook_ads_business'];
            /*Tổng all khách hàng từ GG*/
            $data[$user]['total_second_customer_google'] = 0;
            $data[$user]['total_second_customer_google'] += $data_user[$user]['total_second_customer_google_personal'];
            $data[$user]['total_second_customer_google'] += $data_user[$user]['total_second_customer_google_ads_personal'];
            $data[$user]['total_second_customer_google'] += $data_user[$user]['total_second_customer_google_business'];
            $data[$user]['total_second_customer_google'] += $data_user[$user]['total_second_customer_google_ads_business'];
            /*tổng all khách hàng từ FB*/
            $data[$user]['total_second_customer_facebook'] = 0;
            $data[$user]['total_second_customer_facebook'] += $data_user[$user]['total_second_customer_facebook_ads_personal'];
            $data[$user]['total_second_customer_facebook'] += $data_user[$user]['total_second_customer_facebook_ads_business'];
            /*Tổng all giá trị*/
            $data[$user]['total_amount_all'] = 0;
            $data[$user]['total_amount_all'] += $data[$user]['total_second_customer_amount'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_google_personal'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_google_ads_personal'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_facebook_ads_personal'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_google_business'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_google_ads_business'];
            $data[$user]['total_amount_all'] += $data_user[$user]['total_first_amount_facebook_ads_business'];
            /* tổng all phí dịch vụ*/
            $data[$user]['total_service_change_all'] = 0;
            $data[$user]['total_service_change_all'] += $data[$user]['total_second_customer_service_change'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_google_personal'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_google_ads_personal'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_facebook_ads_personal'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_google_business'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_google_ads_business'];
            $data[$user]['total_service_change_all'] += $data_user[$user]['total_first_service_change_facebook_ads_business'];

            /* First personal*/
            $data[$user]['total_first_amount_google_personal'] = $data_user[$user]['total_first_amount_google_personal'];
            $data[$user]['total_first_service_change_google_personal'] = $data_user[$user]['total_first_service_change_google_personal'];
            $data[$user]['total_first_customer_google_personal'] = $data_user[$user]['total_first_customer_google_personal'];

            $data[$user]['total_first_amount_google_ads_personal'] = $data_user[$user]['total_first_amount_google_ads_personal'];
            $data[$user]['total_first_service_change_google_ads_personal'] = $data_user[$user]['total_first_service_change_google_ads_personal'];
            $data[$user]['total_first_customer_google_ads_personal'] = $data_user[$user]['total_first_customer_google_ads_personal'];

            $data[$user]['total_first_amount_facebook_ads_personal'] = $data_user[$user]['total_first_amount_facebook_ads_personal'];
            $data[$user]['total_first_service_change_facebook_ads_personal'] = $data_user[$user]['total_first_service_change_facebook_ads_personal'];
            $data[$user]['total_first_customer_facebook_ads_personal'] = $data_user[$user]['total_first_customer_facebook_ads_personal'];

            /* First business*/
            $data[$user]['total_first_amount_google_business'] = $data_user[$user]['total_first_amount_google_business'];
            $data[$user]['total_first_service_change_google_business'] = $data_user[$user]['total_first_service_change_google_business'];
            $data[$user]['total_first_customer_google_business'] = $data_user[$user]['total_first_customer_google_business'];

            $data[$user]['total_first_amount_google_ads_business'] = $data_user[$user]['total_first_amount_google_ads_business'];
            $data[$user]['total_first_service_change_google_ads_business'] = $data_user[$user]['total_first_service_change_google_ads_business'];
            $data[$user]['total_first_customer_google_ads_business'] = $data_user[$user]['total_first_customer_google_ads_business'];

            $data[$user]['total_first_amount_facebook_ads_business'] = $data_user[$user]['total_first_amount_facebook_ads_business'];
            $data[$user]['total_first_service_change_facebook_ads_business'] = $data_user[$user]['total_first_service_change_facebook_ads_business'];
            $data[$user]['total_first_customer_facebook_ads_business'] = $data_user[$user]['total_first_customer_facebook_ads_business'];


        }
        return $data ?? [];
    }

    private function getResult($weekStartDate, $weekEndDate): LazyCollection
    {
        return User::query()->select
        (
            'users.id as user_id',
            'orders.id as order_id',
            'users.name as user_name',
            'users.phone_number as user_phone',
            'customers.id as customer_id',
            'orders.created_at as order_created',
            'customers.via as social',
            'customers.business_type',
            'customers.name as customer_name',
            DB::raw('(orders.order_cost + orders.inspection_cost
                                                + orders.international_shipping_cost
                                                + orders.order_fee
                                                + (orders.china_shipping_cost * orders.exchange_rate)
                                                - orders.discount_cost
                                                ) as amount'
            ),
            DB::raw('(orders.order_fee) as service_charge')
        )
        ->leftJoin('customers','customers.staff_group_id','=','users.id')
        ->leftJoin('orders','orders.customer_id','=','customers.id')
        ->where('orders.status', OrderStatus::DAT_HANG)
        ->whereIn('customers.via',[
            ChatBiz::GOOGLE_ADS,
            ChatBiz::FACEBOOK_ADS,
            ChatBiz::GOOGLE
        ])
        ->whereIn('customers.business_type',[
            ChatBiz::PERSONAL,
            ChatBiz::BUSINESS
        ])
        ->whereNull('orders.deleted_at')
        ->whereBetween('orders.date_ordered',[$weekStartDate, $weekEndDate])
        ->whereIn('users.phone_number', ReportCustomerOrderSaleStaff::acceptStaff())
        ->orderBy('orders.date_ordered', 'DESC')
        ->cursor();
    }

    private function getUser(): array
    {
        return User::query()->select(
            'users.id as id',
            'users.name as user_name',
            'users.phone_number as user_phone'
        )
        ->whereIn('users.phone_number', ReportCustomerOrderSaleStaff::acceptStaff())
        ->get()
        ->toArray();
    }

    public function insert($data): bool
    {
        foreach ($data as $key => $value) {
            CustomerOrderSaleStaff::query()->updateOrCreate(
                [
                    'user_id' => $key,
                    'week' => $value['week'],
                    'month' => $value['month'],
                    'year' => $value['year']
                ],
                $value
            );
        }

        return true;
    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return CustomerOrderSaleStaff::query()->where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->delete();
    }

    public function getReport($data): array
    {
       $results = CustomerOrderSaleStaff::query()->where('week', $data['weekEnd'])
           ->where('month', $data['monthEnd'])
           ->where('year', $data['yearEnd'])
           ->orderBy('user_name')
           ->get();
       $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($data['yearEnd'], $data['monthEnd'], $data['weekEnd']);
       $total_second_customer_amount = $results->sum('total_second_customer_amount') ?? 0;
       $total_second_customer_service_change = $results->sum('total_second_customer_service_change') ?? 0;
       $total_second_customer_google = $results->sum('total_second_customer_google') ?? 0;
       $total_second_customer_facebook = $results->sum('total_second_customer_facebook') ?? 0;
       $total_amount_all = $results->sum('total_amount_all') ?? 0;
       $total_service_change_all = $results->sum('total_service_change_all') ?? 0;
       $total_first_amount_google_personal = $results->sum('total_first_amount_google_personal') ?? 0;
       $total_first_service_change_google_personal = $results->sum('total_first_service_change_google_personal') ?? 0;
       $total_first_customer_google_personal = $results->sum('total_first_customer_google_personal') ?? 0;
       $total_first_amount_google_ads_personal = $results->sum('total_first_amount_google_ads_personal') ?? 0;
       $total_first_service_change_google_ads_personal = $results->sum('total_first_service_change_google_ads_personal') ?? 0;
       $total_first_customer_google_ads_personal = $results->sum('total_first_customer_google_ads_personal') ?? 0;
       $total_first_amount_facebook_ads_personal = $results->sum('total_first_amount_facebook_ads_personal') ?? 0;
       $total_first_service_change_facebook_ads_personal = $results->sum('total_first_service_change_facebook_ads_personal') ?? 0;
       $total_first_customer_facebook_ads_personal = $results->sum('total_first_customer_facebook_ads_personal') ?? 0;
       $total_first_amount_google_business = $results->sum('total_first_amount_google_business') ?? 0;
       $total_first_service_change_google_business = $results->sum('total_first_service_change_google_business') ?? 0;
       $total_first_customer_google_business = $results->sum('total_first_customer_google_business') ?? 0;
       $total_first_amount_google_ads_business = $results->sum('total_first_amount_google_ads_business') ?? 0;
       $total_first_service_change_google_ads_business = $results->sum('total_first_service_change_google_ads_business') ?? 0;
       $total_first_customer_google_ads_business = $results->sum('total_first_customer_google_ads_business') ?? 0;
       $total_first_amount_facebook_ads_business = $results->sum('total_first_amount_facebook_ads_business') ?? 0;
       $total_first_service_change_facebook_ads_business = $results->sum('total_first_service_change_facebook_ads_business') ?? 0;
       $total_first_customer_facebook_ads_business = $results->sum('total_first_customer_facebook_ads_business') ?? 0;

       $totalAll = [
            "total_second_customer_amount" => ($total_second_customer_amount),
            "total_second_customer_service_change" => ($total_second_customer_service_change),
            "total_second_customer_google" => ($total_second_customer_google),
            "total_second_customer_facebook" => ($total_second_customer_facebook),
            "total_amount_all" => ($total_amount_all),
            "total_service_change_all" => ($total_service_change_all),
            "total_first_amount_google_personal" => ($total_first_amount_google_personal),
            "total_first_service_change_google_personal" => ($total_first_service_change_google_personal),
            "total_first_customer_google_personal" => ($total_first_customer_google_personal),
            "total_first_amount_google_ads_personal" => ($total_first_amount_google_ads_personal),
            "total_first_service_change_google_ads_personal" => ($total_first_service_change_google_ads_personal),
            "total_first_customer_google_ads_personal" => ($total_first_customer_google_ads_personal),
            "total_first_amount_facebook_ads_personal" => ($total_first_amount_facebook_ads_personal),
            "total_first_service_change_facebook_ads_personal" => ($total_first_service_change_facebook_ads_personal),
            "total_first_customer_facebook_ads_personal" => ($total_first_customer_facebook_ads_personal),
            "total_first_amount_google_business" => ($total_first_amount_google_business),
            "total_first_service_change_google_business" => ($total_first_service_change_google_business),
            "total_first_customer_google_business" => ($total_first_customer_google_business),
            "total_first_amount_google_ads_business" => ($total_first_amount_google_ads_business),
            "total_first_service_change_google_ads_business" => ($total_first_service_change_google_ads_business),
            "total_first_customer_google_ads_business" => ($total_first_customer_google_ads_business),
            "total_first_amount_facebook_ads_business" => ($total_first_amount_facebook_ads_business),
            "total_first_service_change_facebook_ads_business" => ($total_first_service_change_facebook_ads_business),
            "total_first_customer_facebook_ads_business" => ($total_first_customer_facebook_ads_business),
            'user_name' => 'Tổng',
            'user_id' => ''
        ];

       $results = $results->toArray();
       $results[] = $totalAll;
       $formatData = [];
       foreach ($results as $key => $value) {
            $formatData[$key]['total_first_amount_google_personal'] = number_format(
                $value['total_first_amount_google_personal']
                + $value['total_first_amount_google_ads_personal']
            );
            $formatData[$key]['total_first_service_change_google_personal'] = number_format(
                $value['total_first_service_change_google_personal']
                + $value['total_first_service_change_google_ads_personal']
            );
            $formatData[$key]['total_first_customer_google_personal'] = number_format(
                $value['total_first_customer_google_personal']
                + $value['total_first_customer_google_ads_personal']
            );
            $formatData[$key]['total_first_amount_google_business'] = number_format(
                $value['total_first_amount_google_business']
                + $value['total_first_amount_google_ads_business']
            );
            $formatData[$key]['total_first_service_change_google_business'] = number_format(
                $value['total_first_service_change_google_business']
                + $value['total_first_service_change_google_ads_business']
            );
            $formatData[$key]['total_first_customer_google_business'] = number_format(
                $value['total_first_customer_google_business']
                + $value['total_first_customer_google_ads_business']
            );

            $formatData[$key]['total_first_amount_facebook_ads_business'] = number_format($value['total_first_amount_facebook_ads_business']);
            $formatData[$key]['total_first_service_change_facebook_ads_business'] = number_format($value['total_first_service_change_facebook_ads_business']);
            $formatData[$key]['total_first_customer_facebook_ads_business'] = number_format($value['total_first_customer_facebook_ads_business']);

            $formatData[$key]['total_first_amount_facebook_ads_personal'] = number_format($value['total_first_amount_facebook_ads_personal']);
            $formatData[$key]['total_first_service_change_facebook_ads_personal'] = number_format($value['total_first_service_change_facebook_ads_personal']);
            $formatData[$key]['total_first_customer_facebook_ads_personal'] = number_format($value['total_first_customer_facebook_ads_personal']);

            $formatData[$key]['total_second_customer_amount'] = number_format($value['total_second_customer_amount']);
            $formatData[$key]['total_second_customer_service_change'] = number_format($value['total_second_customer_service_change']);
            $formatData[$key]['total_second_customer_google'] = number_format($value['total_second_customer_google']);
            $formatData[$key]['total_second_customer_facebook'] = number_format($value['total_second_customer_facebook']);

            $formatData[$key]['total_amount_all'] = number_format($value['total_amount_all']);
            $formatData[$key]['total_service_change_all'] = number_format($value['total_service_change_all']);
            $formatData[$key]['user_id'] = $value['user_id'] ;
            $formatData[$key]['user_name'] = $value['user_name'];
        }
       $res['format'] = $formatData;
       $res['time'] = [
           'start_date' => date('d-m-Y', strtotime($calculatorDatetime['week_start_date'])),
           'end_date' => date('d-m-Y', strtotime($calculatorDatetime['week_end_date'])),
       ];
       return $res;
    }

    private function calculatorSecond($arrayCustomerSecond, $arrayUser): array
    {
        $arrayValues = array_values($arrayCustomerSecond);
        $arrayMerges = array_merge(...$arrayValues);
        foreach ($arrayUser as $user) {
            foreach ($arrayMerges as $valS) {
                if ($valS['user_id'] == $user) {
                    if ($valS['social'] == ChatBiz::GOOGLE_ADS) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataGoogleAds[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataGoogleAds[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                    if ($valS['social'] == ChatBiz::GOOGLE) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataGoogle[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataGoogle[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                    if ($valS['social'] == ChatBiz::FACEBOOK_ADS) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataFacebook[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataFacebook[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                }
            }
        }
        return [
            'data_second_google' => $dataGoogle ?? [],
            'data_second_google_ads' => $dataGoogleAds ?? [],
            'data_second_facebook_ads' => $dataFacebook ?? []
        ];
    }

    private function calculatorFirst($arrayCustomerFirst, $arrayUser): array
    {
        $arrayValues = array_values($arrayCustomerFirst);
        foreach ($arrayUser as $user) {
            foreach ($arrayValues as $valS) {
                if ($valS['user_id'] == $user) {
                    if ($valS['social'] == ChatBiz::GOOGLE_ADS) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataGoogleAds[$user][ChatBiz::GOOGLE_ADS][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataGoogleAds[$user][ChatBiz::GOOGLE_ADS][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                    if ($valS['social'] == ChatBiz::GOOGLE) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataGoogle[$user][ChatBiz::GOOGLE][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataGoogle[$user][ChatBiz::GOOGLE][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                    if ($valS['social'] == ChatBiz::FACEBOOK_ADS) {
                        if ($valS['business_type'] == ChatBiz::PERSONAL) {
                            $dataFacebook[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::PERSONAL][] = $valS;
                        }
                        if ($valS['business_type'] == ChatBiz::BUSINESS) {
                            $dataFacebook[$user][ChatBiz::FACEBOOK_ADS][ChatBiz::BUSINESS][] = $valS;
                        }
                    }
                }
            }
        }
        return [
            'data_first_google' => $dataGoogle ?? [],
            'data_first_google_ads' => $dataGoogleAds ?? [],
            'data_first_facebook_ads' => $dataFacebook ?? []
        ];
    }
}
