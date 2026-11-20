<?php

namespace App\Services;

use App\Helpers\OrderStatus;
use App\Models\Customer;
use App\Models\Label;
use App\Models\Order;
use App\Models\ReportCustomerOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Constants\OrderConstant;
use App\Constants\CustomerConstant;

class ReportCustomerOrders
{
    function __construct()
    {
    }

    private function getUserGroup($group_id = false, $options = []): array
    {
        $query = User::query();
        $query = $query->whereExists(function ($query) use ($group_id, $options){
            $query->select("users_group.id")
                ->from('users_group')
                ->whereRaw('users_group.user_id = users.id');
            if ($group_id) {
                $query->where('users_group.group_id', $group_id);
            }
            if (!empty($options['month'])) {
                if (is_array($options['month'])) {
                    $query->whereIn('users_group.month', $options['month']);
                } else {
                    $query->where('users_group.month', $options['month']);
                }
            }
        })
            ->where('status', 1)
            ->select('id');
        return $query->pluck('id')
            ->toArray();
    }

    public function cronjobInsertData()
    {
        $currentDate = date('Y-m-d');
        $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
        $start_date = '';
        $end_date = '';
        foreach ($listDates as $listDate) {
            if (
                strtotime($listDate[1]) >= strtotime($currentDate)
                && strtotime($listDate[0]) <= strtotime($currentDate)
            ) {
                $start_date = $listDate[0]. ' 00:00:00';
                $end_date = $listDate[1]. ' 23:59:59';
                break;
            }
        }
        $weekStartDate = $start_date;
        $weekEndDate = $end_date;
        $this->processData(
            $weekStartDate,
            $weekEndDate
        );
    }

    public function cronjobInsertDataMany()
    {
        $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
        foreach ($listDates as $listDate) {
            $saturday = $listDate[0] . ' 00:00:00';
            $friday = $listDate[1] . ' 23:59:59';
            $weekStartDate = $saturday;
            $weekEndDate = $friday;
            $this->processData(
                $weekStartDate,
                $weekEndDate
            );
        }
    }

    private function processData($start_date, $end_date)
    {
        $user_group_ids = $this->getUserGroup();
        $formatData =  $this->formatData($user_group_ids, $start_date, $end_date) ;
        $totalCustomer = $this->getTotalCustomer($start_date, $end_date);
        $totalCustomerLabel = $this->getTotalCustomerByLabel($start_date, $end_date);
        $totalCustomerOrdered = $this->getTotalCustomerOrdered($start_date, $end_date);
        $data = $this->mappingData(
            $formatData,
            $totalCustomer,
            $totalCustomerLabel,
            $totalCustomerOrdered
        );

        $this->insert($data);
    }

    private function mappingData(
        $formatData,
        $totalCustomer,
        $totalCustomerLabel,
        $totalCustomerOrdered
    ): array
    {
        foreach ($formatData as $keySource => &$sources) {
            foreach ($sources as $keyStaff => &$staff) {
                foreach($totalCustomer as $item) {
                    if ($keySource == $item->source
                        && $keyStaff == $item->staff_group_id
                    ) {
                        if(array_key_exists($item->staff_group_id, $sources)) {
                            $sources[$item->staff_group_id]['total_customer'] += $item->total_customer;
                        }else {
                            $sources[0]['total_customer'] += $item->total_customer;
                        }
                    }
                }
            }
        }
        foreach ($formatData as $keySource => &$sources) {
            foreach ($sources as $keyStaff => &$staff) {
                foreach($totalCustomerLabel as $item) {
                    if ($keySource == $item->source
                        && $keyStaff == $item->staff_group_id
                    ) {
                        if(array_key_exists($item->staff_group_id, $sources)) {
                            $sources[$item->staff_group_id]['total_customer'] += $item->total_customer;
                        }else {
                            $sources[0]['total_customer'] += $item->total_customer;
                        }
                    }
                }
            }
        }
        foreach ($formatData as $keySource => &$sources) {
            foreach ($sources as $keyStaff => &$staff) {
                foreach($totalCustomerOrdered as $item) {
                    if ($keySource == $item->source
                        && $keyStaff == $item->staff_group_id
                    ) {
                        if(array_key_exists($item->staff_group_id, $sources)) {
                            $sources[$item->staff_group_id]['total_customer_order'] += $item->total_customer;
                            $sources[$item->staff_group_id]['total_order'] += $item->total_order;
                            $sources[$item->staff_group_id]['revenue'] += $item->total_price;
                            $sources[$item->staff_group_id]['revenue_official'] += $item->total_price_official;
                            $sources[$item->staff_group_id]['revenue_official_partner'] += $item->total_price_official_partner;
                            $sources[$item->staff_group_id]['service_charge'] += $item->service_charge;
                            $sources[$item->staff_group_id]['service_charge_official'] += $item->service_charge_official;
                            $sources[$item->staff_group_id]['service_charge_official_partner'] += $item->service_charge_official_partner;
                        }else {
                            $sources[0]['total_customer_order'] += $item->total_customer;
                            $sources[0]['total_order'] += $item->total_order;
                            $sources[0]['revenue'] += $item->total_price;
                            $sources[0]['revenue_official'] += $item->total_price_official;
                            $sources[0]['revenue_official_partner'] += $item->total_price_official_partner;
                            $sources[0]['service_charge'] += $item->service_charge;
                            $sources[0]['service_charge_official'] += $item->service_charge_official;
                            $sources[0]['service_charge_official_partner'] += $item->service_charge_official_partner;
                        }
                    }
                }
            }
        }
        return array_values($formatData);
    }

    private function formatData($groups,  $saturday, $friday): array
    {
        $sources = $this->getSource();
        $data = [];
        foreach ($groups as $group) {
            foreach ($sources as $keySource => $source){
                $data[$keySource][$group]['staff_group_id'] = $group;
                $data[$keySource][$group]['source'] = $keySource;
                $data[$keySource][$group]['total_customer'] = 0;
                $data[$keySource][$group]['total_customer_order'] = 0;
                $data[$keySource][$group]['total_order'] = 0;
                $data[$keySource][$group]['revenue'] = 0;
                $data[$keySource][$group]['revenue_official'] = 0;
                $data[$keySource][$group]['revenue_official_partner'] = 0;
                $data[$keySource][$group]['service_charge'] = 0;
                $data[$keySource][$group]['service_charge_official'] = 0;
                $data[$keySource][$group]['service_charge_official_partner'] = 0;
                $data[$keySource][$group]['start_date'] = $saturday;
                $data[$keySource][$group]['end_date'] = $friday;
                // Những KH nào mà nhân viên quản lý không có trong nhóm kinh doanh thì cho hết vào staff_group_id = 0
                $data[$keySource][0]['staff_group_id'] = 0;
                $data[$keySource][0]['source'] = $keySource;
                $data[$keySource][0]['total_customer'] = 0;
                $data[$keySource][0]['total_customer_order'] = 0;
                $data[$keySource][0]['total_order'] = 0;
                $data[$keySource][0]['revenue'] = 0;
                $data[$keySource][0]['revenue_official'] = 0;
                $data[$keySource][0]['revenue_official_partner'] = 0;
                $data[$keySource][0]['service_charge'] = 0;
                $data[$keySource][0]['service_charge_official'] = 0;
                $data[$keySource][0]['service_charge_official_partner'] = 0;
                $data[$keySource][0]['start_date'] = $saturday;
                $data[$keySource][0]['end_date'] = $friday;
            }
        }

        return $data;
    }

    public function insert($data){
        try {
            foreach ($data as $keySource => $sources) {
                foreach ($sources as $keyStaff => $staff) {
                    ReportCustomerOrder::query()
                        ->updateOrCreate(
                            [
                                'staff_group_id' => $keyStaff,
                                'source' => $keySource,
                                'start_date' => $staff['start_date'],
                                'end_date' => $staff['end_date']
                            ],
                            $staff);
                }
            }
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportCustomerOrder::query()->where('week', $list->week)
            ->where('year', $list->year)
            ->where('type','week')
            ->where('month', $list->month)
            ->delete();
    }

    public function deleteMonth($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportCustomerOrder::query()->where('year', $list->year)
            ->where('type','month')
            ->where('month', $list->month)
            ->delete();
    }

    public function getReport($params, $default = true): array
    {
        $time1 = '';
        $time2 = '';
        $time3 = '';
        $startTime1 = '';
        $endTime1 = '';
        $startTime2 = '';
        $endTime2 = '';
        $startTime3 = '';
        $endTime3 = '';
        if($default === true) {
            $listDates = CalculatorWeekReport::calculatorDateReportCustomerVip([]);
            if(count($listDates) > 3) {
                $listDates = array_reverse($listDates);
                $time3 = $listDates[0];
                $time2 = $listDates[1];
                $time1 = $listDates[2];

            }
        } else {
            if (!empty($params['time1'])) {
                $time1 = $params['time1'];
            }
            if (!empty($params['time2'])) {
                $time2 = $params['time2'];
            }
            if (!empty($params['time3'])) {
                $time3 = $params['time3'];
            }
        }
        if ($time3) {
            $startTime3 = $time3[0]. ' 00:00:00';
            $endTime3 = $time3[1]. ' 23:59:59';
        }
        if ($time2) {
            $startTime2 = $time2[0]. ' 00:00:00';
            $endTime2 = $time2[1]. ' 23:59:59';
        }
        if ($time1) {
            $startTime1 = $time1[0]. ' 00:00:00';
            $endTime1 = $time1[1]. ' 23:59:59';
        }

        $dataTime1 = $this->getDataReport($startTime1, $endTime1);
        $dataTime2 = $this->getDataReport($startTime2, $endTime2);
        $dataTime3 = $this->getDataReport($startTime3, $endTime3);

        /*Tổng khách order*/
        $totalCustomerTime1 = data_get($dataTime1, 'total_customer') ?? 0;
        $totalCustomerTime2 = data_get($dataTime2, 'total_customer') ?? 0;
        $totalCustomerTime3 = data_get($dataTime3, 'total_customer') ?? 0;
        $quantityTotalCustomerChange = $totalCustomerTime3 - $totalCustomerTime2;
        $percentTotalCustomer = $totalCustomerTime2 > 0
            ? round($quantityTotalCustomerChange * 100 / $totalCustomerTime2 ) : 0;
        /*Tổng KH đặt hàng*/
        $totalCustomerOrderTime1 = data_get($dataTime1, 'total_customer_order') ?? 0;
        $totalCustomerOrderTime2 = data_get($dataTime2, 'total_customer_order') ?? 0;
        $totalCustomerOrderTime3 = data_get($dataTime3, 'total_customer_order') ?? 0;
        $quantityCustomerOrderChange = $totalCustomerOrderTime3 - $totalCustomerOrderTime2;
        $percentCustomerOrder = $totalCustomerOrderTime2 > 0
            ? round($quantityCustomerOrderChange * 100 / $totalCustomerOrderTime2 ) : 0;

        /*Tổng Order*/
        $totalOrderTime1 = data_get($dataTime1, 'total_order') ?? 0;
        $totalOrderTime2 = data_get($dataTime2, 'total_order') ?? 0;
        $totalOrderTime3 = data_get($dataTime3, 'total_order') ?? 0;
        $quantityTotalOrderChange = $totalOrderTime3 - $totalOrderTime2;
        $percentOrderTotal = $totalOrderTime2 > 0
            ? round($quantityTotalOrderChange * 100 / $totalOrderTime2 ) : 0;

        /* Bán chính ngạch */
        $revenueTime1 = data_get($dataTime1, 'total_revenue') ?? 0;
        $revenueTime2 = data_get($dataTime2, 'total_revenue') ?? 0;
        $revenueTime3 = data_get($dataTime3, 'total_revenue') ?? 0;
        $serviceChargeTime1 = data_get($dataTime1, 'total_service_charge') ?? 0;
        $serviceChargeTime2 = data_get($dataTime2, 'total_service_charge') ?? 0;
        $serviceChargeTime3 = data_get($dataTime3, 'total_service_charge') ?? 0;
        $quantityRevenueChange = $revenueTime3 - $revenueTime2;
        $percentRevenue = $revenueTime2 > 0
            ? round($quantityRevenueChange * 100 / $revenueTime2 ) : 0;
        
        /* Chính ngạch */
        $revenueOfficalTime1 = data_get($dataTime1, 'total_revenue_official') ?? 0;
        $revenueOfficalTime2 = data_get($dataTime2, 'total_revenue_official') ?? 0;
        $revenueOfficalTime3 = data_get($dataTime3, 'total_revenue_official') ?? 0;
        $serviceChargeOfficialTime1 = data_get($dataTime1, 'total_service_charge_official') ?? 0;
        $serviceChargeOfficialTime2 = data_get($dataTime2, 'total_service_charge_official') ?? 0;
        $serviceChargeOfficialTime3 = data_get($dataTime3, 'total_service_charge_official') ?? 0;
        $quantityRevenueChangeOffical = $revenueOfficalTime3 - $revenueOfficalTime2;
        $percentRevenueOffical = $revenueOfficalTime2 > 0
            ? round($quantityRevenueChangeOffical * 100 / $revenueOfficalTime2 ) : 0;
        /* Chính ngạch đối tác	*/
        $revenueTimeOfficalPartner1 = data_get($dataTime1, 'total_revenue_official_partner') ?? 0;
        $revenueTimeOfficalPartner2 = data_get($dataTime2, 'total_revenue_official_partner') ?? 0;
        $revenueTimeOfficalPartner3 = data_get($dataTime3, 'total_revenue_official_partner') ?? 0;
        $serviceChargeOfficialPartnerTime1 = data_get($dataTime1, 'total_service_charge_official_partner') ?? 0;
        $serviceChargeOfficialPartnerTime2 = data_get($dataTime2, 'total_service_charge_official_partner') ?? 0;
        $serviceChargeOfficialPartnerTime3 = data_get($dataTime3, 'total_service_charge_official_partner') ?? 0;
        $quantityRevenueChangeOfficalPartner = $revenueTimeOfficalPartner3 - $revenueTimeOfficalPartner2;
        $percentRevenueOfficalPartner = $revenueTimeOfficalPartner2 > 0
            ? round($quantityRevenueChangeOfficalPartner * 100 / $revenueTimeOfficalPartner2 ) : 0;
        // Giá trị đơn
        $totalTime1 = $revenueTime1 + $revenueOfficalTime1 + $revenueTimeOfficalPartner1;
        $totalTime2 = $revenueTime2 + $revenueOfficalTime2 + $revenueTimeOfficalPartner2;
        $totalTime3 = $revenueTime3 + $revenueOfficalTime3 + $revenueTimeOfficalPartner3;
        $quantityRevenueChangeAll = $totalTime3 - $totalTime2;
        $percentRevenueAll = $totalTime2 > 0
            ? round($quantityRevenueChangeAll * 100 / $totalTime2) : 0;
        /* AOV*/
        $aovTime1 = $totalCustomerOrderTime1 > 0 ? round($totalTime1 / $totalCustomerOrderTime1 ) : 0;
        $aovTime2 = $totalCustomerOrderTime2 > 0 ? round($totalTime2 / $totalCustomerOrderTime2 ) : 0;
        $aovTime3 = $totalCustomerOrderTime3 > 0 ? round($totalTime3 / $totalCustomerOrderTime3 ) : 0;
        $quantityAov = $aovTime3 - $aovTime2;
        $percentAov = $aovTime2 > 0 ? round($quantityAov*100/$aovTime2) : 0;

         /* Tỷ lệ*/
        $percentTime1 = $totalCustomerTime1 > 0 ? round($totalCustomerOrderTime1 * 100 / $totalCustomerTime1, 3) : 0;
        $percentTime2 = $totalCustomerTime2 > 0 ? round($totalCustomerOrderTime2 * 100 / $totalCustomerTime2, 3) : 0;
        $percentTime3 = $totalCustomerTime3 > 0 ? round($totalCustomerOrderTime3 * 100 / $totalCustomerTime3, 3) : 0;
        $quantityPercent = $percentTime3 - $percentTime2;
        $percentPercent = $percentTime2 > 0
            ? round($quantityPercent * 100 / $percentTime2) : 0;

        // Tổng phí dịch vụ
        $totalServiceCharge1 = $serviceChargeTime1 + $serviceChargeOfficialTime1 + $serviceChargeOfficialPartnerTime1;
        $totalServiceCharge2 = $serviceChargeTime2 + $serviceChargeOfficialTime2 + $serviceChargeOfficialPartnerTime2;
        $totalServiceCharge3 = $serviceChargeTime3 + $serviceChargeOfficialTime3 + $serviceChargeOfficialPartnerTime3;

        $percentOrderCostTime1 = round(($totalTime1 > 0 ? $revenueTime1/$totalTime1 * 100 : 0), 2);
        $percentOrderCostTime2 = round(($totalTime2 > 0 ? $revenueTime2/$totalTime2 * 100 : 0), 2);
        $percentOrderCostTime3 = round(($totalTime3 > 0 ? $revenueTime3/$totalTime3 * 100 : 0), 2);
        $percentServiceChargeTime1 = round(($totalServiceCharge1 > 0 ? $serviceChargeTime1/$totalServiceCharge1 * 100 : 0), 2);
        $percentServiceChargeTime2 = round(($totalServiceCharge2 > 0 ? $serviceChargeTime2/$totalServiceCharge2 * 100 : 0), 2);
        $percentServiceChargeTime3 = round(($totalServiceCharge3 > 0 ? $serviceChargeTime3/$totalServiceCharge3 * 100 : 0), 2);

        $percentOrderCostOfficalTime1 = round(($totalTime1 > 0 ? $revenueOfficalTime1/$totalTime1 * 100 : 0), 2);
        $percentOrderCostOfficalTime2 = round(($totalTime2 > 0 ? $revenueOfficalTime2/$totalTime2 * 100 : 0), 2);
        $percentOrderCostOfficalTime3 = round(($totalTime3 > 0 ? $revenueOfficalTime3/$totalTime3 * 100 : 0), 2);
        $percentServiceChargeOfficalTime1 = round(($totalServiceCharge1 > 0 ? $serviceChargeOfficialTime1/$totalServiceCharge1 * 100 : 0), 2);
        $percentServiceChargeOfficalTime2 = round(($totalServiceCharge2 > 0 ? $serviceChargeOfficialTime2/$totalServiceCharge2 * 100 : 0), 2);
        $percentServiceChargeOfficalTime3 = round(($totalServiceCharge3 > 0 ? $serviceChargeOfficialTime3/$totalServiceCharge3 * 100 : 0), 2);

        $percentOrderCostOfficalPartnerTime1 = round(($totalTime1 > 0 ? $revenueTimeOfficalPartner1/$totalTime1 * 100 : 0), 2);
        $percentOrderCostOfficalPartnerTime2 = round(($totalTime2 > 0 ? $revenueTimeOfficalPartner2/$totalTime2 * 100 : 0), 2);
        $percentOrderCostOfficalPartnerTime3 = round(($totalTime3 > 0 ? $revenueTimeOfficalPartner3/$totalTime3 * 100 : 0), 2);
        $percentServiceChargeOfficalPartnerTime1 = round(($totalServiceCharge1 > 0 ? $serviceChargeOfficialPartnerTime1/$totalServiceCharge1 * 100 : 0), 2);
        $percentServiceChargeOfficalPartnerTime2 = round(($totalServiceCharge2 > 0 ? $serviceChargeOfficialPartnerTime2/$totalServiceCharge2 * 100 : 0), 2);
        $percentServiceChargeOfficalPartnerTime3 = round(($totalServiceCharge3 > 0 ? $serviceChargeOfficialPartnerTime3/$totalServiceCharge3 * 100 : 0), 2);

        $isDisplayTime1 =  'Thời gian tham chiếu 1';
        $isDisplayTime2 =  'Thời gian tham chiếu 2';
        $isDisplayTime3 =  'Thời gian xem';
        $data['format'] = [
            [

                'data_one' => '',
                'data_two' => $isDisplayTime1.' ( '.$this->formatDate($startTime1) .' đến '.$this->formatDate($endTime1).' )',
                'data_three' => $isDisplayTime2.' ( '.$this->formatDate($startTime2) .' đến '.$this->formatDate($endTime2).' )',
                'data_four' => $isDisplayTime3.' ( '.$this->formatDate($startTime3) .' đến '.$this->formatDate($endTime3).' )',
                'data_five' => 'Số lượng tăng/ giảm',
                'data_six' => '% Tăng trưởng',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Tổng khách order',
                'data_two' => number_format($totalCustomerTime1,0,',','.'),
                'data_three' => number_format($totalCustomerTime2,0,',','.'),
                'data_four' => number_format($totalCustomerTime3,0,',','.'),
                'data_five' => $quantityTotalCustomerChange,
                'data_six' => $percentTotalCustomer.'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Tổng KH đặt hàng',
                'data_two' => number_format($totalCustomerOrderTime1,0,',','.'),
                'data_three' => number_format($totalCustomerOrderTime2,0,',','.'),
                'data_four' => number_format($totalCustomerOrderTime3,0,',','.'),
                'data_five' => $quantityCustomerOrderChange,
                'data_six' => $percentCustomerOrder.'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Số lượng đơn',
                'data_two' => number_format($totalOrderTime1,0,',','.'),
                'data_three' => number_format($totalOrderTime2,0,',','.'),
                'data_four' => number_format($totalOrderTime3,0,',','.'),
                'data_five' => $quantityTotalOrderChange,
                'data_six' => $percentOrderTotal.'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Giá trị đơn',
                'data_two' => number_format($totalTime1,0,',','.'),
                'data_three' => number_format($totalTime2,0,',','.'),
                'data_four' => number_format($totalTime3,0,',','.'),
                'data_five' => number_format($quantityRevenueChangeAll,0,',','.'),
                'data_six' => ($percentRevenueAll).'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Bán chính ngạch',
                'data_two' => number_format($revenueTime1,0,',','.')." (${percentOrderCostTime1}%)",
                'data_three' => number_format($revenueTime2,0,',','.')." (${percentOrderCostTime2}%)",
                'data_four' => number_format($revenueTime3,0,',','.')." (${percentOrderCostTime3}%)",
                'data_five' => number_format($quantityRevenueChange,0,',','.'),
                'data_six' => ($percentRevenue).'%',
                'data_two_2' => number_format($serviceChargeTime1,0,',','.')." (${percentServiceChargeTime1}%)",
                'data_three_2' => number_format($serviceChargeTime2,0,',','.')." (${percentServiceChargeTime2}%)",
                'data_four_2' => number_format($serviceChargeTime3,0,',','.')." (${percentServiceChargeTime3}%)",
            ],
            [
                'data_one' => 'Chính ngạch',
                'data_two' => number_format($revenueOfficalTime1,0,',','.')." (${percentOrderCostOfficalTime1}%)",
                'data_three' => number_format($revenueOfficalTime2,0,',','.')." (${percentOrderCostOfficalTime2}%)",
                'data_four' => number_format($revenueOfficalTime3,0,',','.')." (${percentOrderCostOfficalTime3}%)",
                'data_five' => number_format($quantityRevenueChangeOffical,0,',','.'),
                'data_six' => ($percentRevenueOffical).'%',
                'data_two_2' => number_format($serviceChargeOfficialTime1,0,',','.')." (${percentServiceChargeOfficalTime1}%)",
                'data_three_2' => number_format($serviceChargeOfficialTime2,0,',','.')." (${percentServiceChargeOfficalTime2}%)",
                'data_four_2' => number_format($serviceChargeOfficialTime3,0,',','.')." (${percentServiceChargeOfficalTime3}%)",
            ],
            [
                'data_one' => 'Chính ngạch đối tác',
                'data_two' => number_format($revenueTimeOfficalPartner1,0,',','.')." (${percentOrderCostOfficalPartnerTime1}%)",
                'data_three' => number_format($revenueTimeOfficalPartner2,0,',','.')." (${percentOrderCostOfficalPartnerTime2}%)",
                'data_four' => number_format($revenueTimeOfficalPartner3,0,',','.')." (${percentOrderCostOfficalPartnerTime3}%)",
                'data_five' => number_format($quantityRevenueChangeOfficalPartner,0,',','.'),
                'data_six' => ($percentRevenueOfficalPartner).'%',
                'data_two_2' => number_format($serviceChargeOfficialPartnerTime1,0,',','.')." (${percentServiceChargeOfficalPartnerTime1}%)",
                'data_three_2' => number_format($serviceChargeOfficialPartnerTime2,0,',','.')." (${percentServiceChargeOfficalPartnerTime2}%)",
                'data_four_2' => number_format($serviceChargeOfficialPartnerTime3,0,',','.')." (${percentServiceChargeOfficalPartnerTime3}%)",
            ],
            [
                'data_one' => 'AOV',
                'data_two' =>  number_format($aovTime1,0,',','.'),
                'data_three' => number_format($aovTime2,0,',','.'),
                'data_four' => number_format($aovTime3,0,',','.'),
                'data_five' => number_format($aovTime3 - $aovTime2, 0, ',','.'),
                'data_six' => ($percentAov).'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
            [
                'data_one' => 'Tỷ lệ khách đặt hàng',
                'data_two' =>  ($percentTime1).'%',
                'data_three' => ($percentTime2).'%',
                'data_four' => ($percentTime3).'%',
                'data_five' => ($quantityPercent).'%',
                'data_six' => ($percentPercent).'%',
                'data_two_2' => '',
                'data_three_2' => '',
                'data_four_2' => '',
            ],
        ];
        $tableFirst =
        [
            [
                'name' => $isDisplayTime1,
                'data' => [(int)$totalCustomerTime1, (int)$totalCustomerOrderTime1]
            ],
            [
                'name' => $isDisplayTime2,
                'data' => [(int)$totalCustomerTime2, (int)$totalCustomerOrderTime2]
            ],
            [
                'name' => $isDisplayTime3,
                'data' => [(int)$totalCustomerTime3, (int)$totalCustomerOrderTime3]
            ]
        ];
        $tableSecond =
        [
            [
                'name' => $isDisplayTime1,
                'data' => [(int)$totalTime1, (int)$totalServiceCharge1]
            ],
            [
                'name' => $isDisplayTime2,
                'data' => [(int)$totalTime2, (int)$totalServiceCharge2]
            ],
            [
                'name' => $isDisplayTime3,
                'data' => [(int)$totalTime3, (int)$totalServiceCharge3]
            ]
        ];
        $data['tableFirst'] = $tableFirst;
        $data['tableSecond'] = $tableSecond;
        return $data;
    }

    private function getTotalCustomer($start_date, $end_date)
    {
        return Customer::query()
            ->select(
                DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
                'customers.staff_group_id',
                'customers.source'
            )
            ->where('customers.status', 1)
            ->join('orders', function($join) use ($start_date, $end_date){
                $join->on('orders.customer_id', '=', 'customers.id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date]);
                }
            )->groupBy('customers.staff_group_id', 'customers.source')->get();
    }

    public function getTotalCustomerByLabel($start_date, $end_date)
    {
        $label_ids = Label::whereIn('code', ['THAM_KHAO', 'DANG_FOLLOW'])->pluck('id')->toArray();
        return Customer::query()
            ->select(
                DB::raw('COUNT(DISTINCT customers.id) as total_customer'),
                'customers.staff_group_id',
                'customers.source'
            )
            ->where('customers.status', 1)
            ->whereBetween('customers.created_at', [$start_date, $end_date])
            ->join('customer_label', function($join) use ($label_ids, $start_date, $end_date){
                $join->on('customer_label.customer_id', '=', 'customers.id')
                    ->whereIn('customer_label.label_id', $label_ids);
                }
            )->groupBy('customers.staff_group_id', 'customers.source')->get();
    }
    private function getTotalCustomerOrdered($start_date, $end_date)
    {
        return Customer::query()
            ->select
            (
                DB::raw('COUNT(DISTINCT customers.id) AS total_customer'),
                DB::raw('COUNT(orders.id) as total_order'),
                DB::raw("SUM(IF(orders.official=1,orders.order_cost,0)) as total_price"),
                DB::raw("SUM(IF(orders.official=2 && orders.official_is_partner=0,orders.official_order_cost,0)) as total_price_official"),
                DB::raw("SUM(IF(orders.official=2 && orders.official_is_partner=1,orders.order_cost,0)) as total_price_official_partner"),
                DB::raw("SUM(IF(orders.official=1,orders.order_fee,0)) as service_charge"),
                DB::raw("SUM(IF(orders.official=2 && orders.official_is_partner=0,orders.official_service_fee,0)) as service_charge_official"),
                DB::raw("SUM(IF(orders.official=2 && orders.official_is_partner=1,orders.order_fee,0)) as service_charge_official_partner"),
                'customers.staff_group_id',
                'customers.source'
            )
            ->join('orders', function($join) use ($start_date, $end_date){
                $join->on('orders.customer_id', '=', 'customers.id')
                    ->where('orders.status', OrderConstant::KEY_STATUS_ORDERED);
                    // ->whereIn('orders.status', [
                    //     OrderConstant::KEY_STATUS_DEPOSITED,
                    //     OrderConstant::KEY_STATUS_ORDERED,
                    //     OrderConstant::KEY_STATUS_WAIT_TO_PAY
                    // ]);
                }
            )
            ->join('order_status_times', function($join) use ($start_date, $end_date){
                $join->on('order_status_times.order_id', '=', 'orders.id')
                    ->whereBetween('order_status_times.status_3', [$start_date, $end_date]);
                }
            )
            ->where('customers.status', 1)
            ->groupBy('customers.staff_group_id', 'customers.source')
            ->get();
    }

    private function getDataReport($start_date, $end_date)
    {
        $group_id = request()->input('group_id');
        $source = request()->input('source') ?? '';
        $month_start = date('m/Y', strtotime($start_date));
        $month_end = date('m/Y', strtotime($end_date));
        $user_group_ids = $this->getUserGroup($group_id, ['month' => [$month_start, $month_end]]);
        if ($group_id && empty($user_group_ids)) {
            return [];
        }
        return ReportCustomerOrder::query()
            ->select
            (
                DB::raw('SUM(total_customer) as total_customer'),
                DB::raw('SUM(total_customer_order) as total_customer_order'),
                DB::raw('SUM(total_order) as total_order'),
                DB::raw('SUM(revenue) as total_revenue'),
                DB::raw('SUM(revenue_official) as total_revenue_official'),
                DB::raw('SUM(revenue_official_partner) as total_revenue_official_partner'),
                DB::raw('SUM(service_charge) as total_service_charge'),
                DB::raw('SUM(service_charge_official) as total_service_charge_official'),
                DB::raw('SUM(service_charge_official_partner) as total_service_charge_official_partner')
            )
            ->where([
                'start_date' => $start_date,
                'end_date' => $end_date
            ])
            ->when($group_id, function($q) use ($user_group_ids){
                $q->whereIn('staff_group_id', $user_group_ids);
            })
            ->when($source !== '', function($q) use ($source) {
                $q->where('source', $source);
            })
            ->first();
    }

    private function formatDate($date): string
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('d-m-Y');
    }

    private function getSource(): array
    {
        return CustomerConstant::CUSTOMER_SOURCES;
    }

}
