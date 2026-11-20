<?php

namespace App\Services\Reports;

use GuzzleHttp\Client as Guzzle;

use Carbon\Carbon;
use App\Constants\ViaConstant;
use App\Constants\OrderConstant;

class ReportCheckMarketing
{
    function __construct() {

    }

    public function cronData()
    {
        // Khởi tạo dữ liệu
        $data_report = [];
        $arr_type = [
            'no_interaction_', // Không tương tác được
            'no_need_', // Không có nhu cầu
            'need_but_no_enter_good_', // Có nhu cầu nhưng không nhập được
            'reference_but_no_need_now_', // Có nhu cầu: Tham khảo chưa có nhu cầu ngay
            'reference_', // Tham khảo
            'follow_', // Follow

            'ordered_personal_', // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_', // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_', // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_', // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'livechat',
            'system',
            'zalo',
            'facebook',
            'gg_form',
            'fb_form',
        ];
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                $data_report[$type . $chanel] = 0;
            }
        }

        $guzzle = new Guzzle;
        $url = 'https://api-v2.chatbiz.io/get-data-customer-927sj217j1239x129i388a';

        $now       = Carbon::now();
        $startDate = Carbon::now()->startOfWeek();
        $endDate   = Carbon::now()->endOfWeek();

        $data_report['date_start'] = $startDate->format('Y-m-d');
        $data_report['date_end']   = $endDate->format('Y-m-d');

        // Đếm trong nội bộ hệ thống
        $customerModel = \App::make('App\Models\Customer');
        $orderModel = \App::make('App\Models\Order');
        $orderPackageModel = \App::make('App\Models\OrderPackage');
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $customerDB = $customerModel->where('created_at', '>=', $data_report['date_start'])
                                    ->where('created_at', '<=', $data_report['date_end'])
                                    ->whereNotNull('label_id')
                                    ->where('status', 1)
                                    ->with('label')
                                    ->get();

        if (count($customerDB) > 0) {
            foreach ($customerDB as $key => $customer) {
                $chanel  = 'system';
                $labelDB = $customer->label;
                if (!is_null($labelDB)) {
                    if ($labelDB->code == 'KHONG_TUONG_TAC_DUOC') {
                        $data_report['no_interaction_' . $chanel] += 1;
                    }
                    if ($labelDB->code == 'KHONG_CO_NHU_CAU') {
                        $data_report['no_need_' . $chanel] += 1;
                    }
                    if ($labelDB->code == 'MAT_HANG_KHONG_NHAP_DUOC') {
                        $data_report['need_but_no_enter_good_' . $chanel] += 1;
                    }
                    if ($labelDB->code == 'THAM_KHAO_CHUA_CO_NHU_CAU_NGAY') {
                        $data_report['reference_but_no_need_now_' . $chanel] += 1;
                    }


                    $chanelV2  = $this->getChanelHV($customer);
                    if (!is_null($chanelV2)) {
                        if ($labelDB->code == 'THAM_KHAO') {
                            $data_report['reference_' . $chanelV2] += 1;
                        }
                        if ($labelDB->code == 'DANG_FOLLOW') {
                            $data_report['follow_' . $chanelV2] += 1;
                        }
                        $businessType = $customer->business_type;
                        if (!is_null($businessType)) {
                            // if ($labelDB->code == 'DA_LEN_DON') {
                            //     $data_report['ordered_' . $businessType . '_' . $chanelV2] += 1;
                            // }
                            // if ($labelDB->code == 'KI_GUI') {
                            //     $data_report['consignment_' . $businessType . '_' . $chanelV2] += 1;
                            // }
                            // if ($labelDB->code == 'CHUYEN_TIEN_QUOC_TE') {
                            //     $data_report['pay_help_' . $businessType . '_' . $chanelV2] += 1;
                            // }
                        }
                    }
                }
            }
        }


        // Đếm trông nội bộ hệ thống quy tắc 2
        $customerDB = $customerModel->where('created_at', '>=', $data_report['date_start'])
                                    ->where('created_at', '<=', $data_report['date_end'])
                                    ->where('status', 1)
                                    ->get();
        if (count($customerDB) > 0) {
            foreach ($customerDB as $key => $customer) {
                $chanelV2  = $this->getChanelHV($customer);
                if (!is_null($chanelV2)) {
                    $businessType = $customer->business_type;
                    if (!is_null($businessType)) {
                        // Logic Đã lên đơn
                        $orderOfCustomerCount = $orderModel->where('customer_id', $customer->id)
                                                      ->where('status', OrderConstant::KEY_STATUS_ORDERED)
                                                      ->count();

                        if ($orderOfCustomerCount > 0) {
                            $data_report['ordered_' . $businessType . '_' . $chanelV2] += 1;
                        }

                        // Logic kí gửi
                        $orderPackageOfCustomerCount = $orderPackageModel->where('order_type', 'App\Models\Consignment')
                                                                        ->where('customer_id', $customer->id)
                                                                        ->count();
                        if ($orderPackageOfCustomerCount > 0) {
                            $data_report['consignment_' . $businessType . '_' . $chanelV2] += 1;
                        }

                        // Logic chuyển tiền quốc tế
                        $fundTransactionOfCustomerCount = $fundTransactionModel ->where('customer_code', $customer->code)
                                                                                ->where('fund_type_pay_code', 2)
                                                                                ->count();
                        if ($fundTransactionOfCustomerCount > 0) {
                            $data_report['pay_help_' . $businessType . '_' . $chanelV2] += 1;
                        }
                    }
                }
            }
        }

        // Đếm bên Chatbiz
        $options = [
            'query' => [
                "date_start" => $startDate->format('Y-m-d'),
                "date_end"   => $endDate->format('Y-m-d')
            ]
        ];

        $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        // Tính toán
        if ($result && $result['status_code'] == 200) {
            // Các nhãn Chatbiz
            $CB_KHONG_TUONG_TAC_DUOC             = '899226054299c40274d766e71d781';
            $CB_KHONG_CO_NHU_CAU                 = '8992260542944518d5375ae5d6a73';
            $CB_CO_NHU_CAU_NHUNG_KHONG_NHAP_DUOC = '899226054296083589804111e0af3';
            $CB_KH_ORDER_THAM_KHAO               = '8992260542805911ca27bec6f5fd5'; // Order tham khảo

            foreach ($result['data'] as $key => $item) {
                $labels     = $item['labels'];
                $labels_ids = \Arr::pluck($labels, 'id');
                $chanel     = $this->getChanel($item['type']);

                // Nếu không có channel thì bỏ qua
                if (is_null($chanel)) {
                    continue;
                }
                // Không tương tác được
                if (in_array($CB_KHONG_TUONG_TAC_DUOC, $labels_ids)) {
                    $data_report['no_interaction_' . $chanel] += 1;
                }
                // Không có nhu cầu
                if (in_array($CB_KHONG_CO_NHU_CAU, $labels_ids)) {
                    $data_report['no_need_' . $chanel] += 1;
                }
                // Có nhu cầu nhưng không nhập được
                if (in_array($CB_CO_NHU_CAU_NHUNG_KHONG_NHAP_DUOC, $labels_ids)) {
                    $data_report['need_but_no_enter_good_' . $chanel] += 1;
                }
                // Có nhu cầu: Tham khảo chưa có nhu cầu ngay
                if (in_array($CB_KH_ORDER_THAM_KHAO, $labels_ids)) {
                    $data_report['reference_but_no_need_now_' . $chanel] += 1;
                }
            }
        }

        // Lưu lại
        $reportCheckMarketingModel = \App::make('App\Models\ReportCheckMarketing');
        $reportResult = $reportCheckMarketingModel->where('date_start', $data_report['date_start'])
                                                  ->where('date_end', $data_report['date_end'])
                                                  ->first();
        if (is_null($reportResult)) {
            $data_report['organization_id'] = 1;
            $reportResult =  $reportCheckMarketingModel->create($data_report);
        } else {
            // Loại bỏ 4 hàng đầu tiên của bên GG_Form và FB_Form
            $arr_type = [
                'no_interaction_', // Không tương tác được
                'no_need_', // Không có nhu cầu
                'need_but_no_enter_good_', // Có nhu cầu nhưng không nhập được
                'reference_but_no_need_now_', // Có nhu cầu: Tham khảo chưa có nhu cầu ngay
            ];
            $arr_chanel = [
                'gg_form',
                'fb_form',
            ];
            foreach ($arr_type as $key_1 => $type) {
                foreach ($arr_chanel as $key_2 => $chanel) {
                    unset($data_report[$type . $chanel]);
                }
            }
            $reportResult->fill($data_report)->save();
        }

        // dump($reportResult);
        dd("Đã đếm lại xong: " . date('Y-m-d H:i:s', time()));
    }

    public function getChanel($type)
    {
        $CB_ZALO           = 4;
        $CB_FACE_BOOK_CHAT = 2;
        $CB_LIVE_CHAT      = 1;

        if ($type == $CB_LIVE_CHAT) {
            return 'livechat';
        }
        if ($type == $CB_FACE_BOOK_CHAT) {
            return 'facebook';
        }
        if ($type == $CB_ZALO) {
            return 'zalo';
        }

        return null; // Không xác định
    }

    public function getChanelHV($customer)
    {
        if ($customer->via == ViaConstant::KEY_GOOGLE_FORM) {
            return 'gg_form';
        }
        if ($customer->via == ViaConstant::KEY_FACEBOOK) {
            return 'fb_form';
        }
        if ($customer->via == ViaConstant::KEY_FACEBOOK_ADS) {
            return 'facebook';
        }
        if ($customer->via == ViaConstant::KEY_ZALO) {
            return 'zalo';
        }
        // Khách hàng hệ thống thì chờ

        return null; // Không xác định
    }

    public function getData($reportDB)
    {
        $resultDB = $reportDB->toArray();
        // dump($resultDB);

        $arr_type = [
            'no_interaction_', // Không tương tác được
            'no_need_', // Không có nhu cầu
            'need_but_no_enter_good_', // Có nhu cầu nhưng không nhập được
            'reference_but_no_need_now_', // Có nhu cầu: Tham khảo chưa có nhu cầu ngay
            'reference_', // Tham khảo
            'follow_', // Follow

            'ordered_personal_', // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_', // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_', // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_', // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'livechat',
            'system',
            'zalo',
            'facebook',
            'gg_form',
            'fb_form',
        ];

        // Không có nhu cầu
        $arr_type_no_need = [
            'no_need_', // Không có nhu cầu
            'need_but_no_enter_good_', // Có nhu cầu nhưng không nhập được
        ];
        // Khách đặn hàng
        $arr_type_ordered = [
            'ordered_personal_', // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_', // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_', // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_', // Thanh toán hộ - Khách kinh doanh
        ];

        // Tổng khách hàng nhận được
        $resultDB['total_customer_revice_sum'] = 0;
        foreach ($arr_chanel as $key_2 => $chanel) {
            $resultDB['total_customer_revice_' . $chanel] = 0;
            foreach ($arr_type as $key_1 => $type) {
                $resultDB['total_customer_revice_' . $chanel] += $resultDB[$type . $chanel];
            }
            $resultDB['total_customer_revice_sum'] += $resultDB['total_customer_revice_' . $chanel];
        }

        // Cột tổng data
        foreach ($arr_type as $key_1 => $type) {
            $resultDB[$type . 'sum'] = 0;
            foreach ($arr_chanel as $key_2 => $chanel) {
                $resultDB[$type . 'sum'] += $resultDB[$type . $chanel];
            }

            $resultDB[$type . 'sum_percent'] = round($resultDB[$type . 'sum'] / $resultDB['total_customer_revice_sum'] * 100);
        }

        // Tính %
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                if ($resultDB['total_customer_revice_' . $chanel] == 0) {
                    $resultDB[$type . $chanel . '_percent'] = 0;
                } else {
                    $resultDB[$type . $chanel . '_percent'] = round($resultDB[$type . $chanel] / $resultDB['total_customer_revice_' . $chanel] * 100);
                }
            }
        }

        // Tổng khách hàng không có nhu cầu
        $resultDB['total_customer_no_need_sum'] = 0;
        foreach ($arr_chanel as $key_2 => $chanel) {
            $resultDB['total_customer_no_need_' . $chanel] = 0;
            foreach ($arr_type_no_need as $key_1 => $type) {
                $resultDB['total_customer_no_need_' . $chanel] += $resultDB[$type . $chanel];
            }
            $resultDB['total_customer_no_need_sum'] += $resultDB['total_customer_no_need_' . $chanel];
        }

        // Tính %
        $resultDB['total_customer_no_need_sum_percent'] = round($resultDB['total_customer_no_need_sum'] / $resultDB['total_customer_revice_sum'] * 100);

        foreach ($arr_chanel as $key_1 => $chanel) {
            if ($resultDB['total_customer_no_need_' . $chanel] == 0) {
                $resultDB['total_customer_no_need_' . $chanel . '_percent'] = 0;
            } else {
                $resultDB['total_customer_no_need_' . $chanel . '_percent'] = round($resultDB['total_customer_no_need_' . $chanel] / $resultDB['total_customer_no_need_sum'] * 100);

                $resultDB['total_customer_no_need_' . $chanel . '_percent'] = round($resultDB['total_customer_no_need_' . $chanel] / $resultDB['total_customer_revice_' . $chanel] * 100);
            }
        }

        // Tổng khách hàng đặt hàng
        $resultDB['total_customer_ordered_sum'] = 0;
        foreach ($arr_chanel as $key_2 => $chanel) {
            $resultDB['total_customer_ordered_' . $chanel] = 0;
            foreach ($arr_type_ordered as $key_1 => $type) {
                $resultDB['total_customer_ordered_' . $chanel] += $resultDB[$type . $chanel];
            }
            $resultDB['total_customer_ordered_sum'] += $resultDB['total_customer_ordered_' . $chanel];
        }


        ksort($resultDB);
        // dump($resultDB);
        return $resultDB;
    }

}
