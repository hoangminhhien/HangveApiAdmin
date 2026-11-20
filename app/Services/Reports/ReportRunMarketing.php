<?php

namespace App\Services\Reports;

use GuzzleHttp\Client as Guzzle;

use Carbon\Carbon;
use App\Constants\ViaConstant;
use App\Constants\OrderConstant;

use App\Models\Label;
use App\Constants\ChatbizConstant;
use App\Constants\ReportConstant;

class ReportRunMarketing
{
    function __construct() {

    }

    public function cronData()
    {
        $guzzle = new Guzzle;
        $url = 'http://172.18.0.14/get-data-customer-by-conversation-08012025-kmoqujr';
        $url = 'https://api-v2.chatbiz.io/get-data-customer-by-conversation-08012025-kmoqujr';

        $now       = Carbon::now();
        $startDate = Carbon::now()->startOfWeek();
        $endDate   = Carbon::now()->endOfWeek();

        // $data_report['date_start'] = '2024-12-02 00:00:00';
        // $data_report['date_end']   = '2024-12-08 23:59:59';

        $startDateCheck = $startDate->format('Y-m-d') . ' 00:00:00';
        $endDateCheck   = $endDate->format('Y-m-d') . ' 23:59:59';

        $userModel = \App::make('App\Models\User');
        $arr_data_report = []; // Tổng các thế thoại

        // Đếm trong nội bộ hệ thống
        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->where('created_at', '>=', $startDateCheck)
                                    ->where('created_at', '<=', $endDateCheck)
                                    ->where('status', 1)
                                    ->with('labels')
                                    ->get();

        $collection = collect($customerDBs->toArray());
        $grouped = $collection->groupBy('staff_group_id');
        $grouped->all();

        foreach ($grouped as $key_group_1 => $group) {
            $data_report = $this->cronDataOne($group);
            $data_report['date_start'] = $startDate->format('Y-m-d');
            $data_report['date_end']   = $endDate->format('Y-m-d');
            $userDB = $userModel->where('id', $key_group_1)->where('status', 1)->first();
            if (!is_null($userDB)) {
                $arr_data_report[$userDB->email] = $data_report;
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

        if ($result && $result['status_code'] == 200) {
            $collection = collect($result['data']);
            $grouped = $collection->groupBy('agent_email');
            $grouped->all();

            foreach ($grouped as $agent_email => $group) {
                // Lấy email tương ứng
                $email_new = $this->mappingUser($agent_email);

                // Kiểm tra xem có trùng không
                $data_report_1 = null;
                if (array_key_exists($email_new, $arr_data_report)) {
                    $data_report_1 = $arr_data_report[$email_new];
                }

                // Tính toán
                $data_report_2 = $this->cronDataChatbiz($group, $data_report_1);

                // Bổ sung thêm thời gian nếu không có email trong mảng gốc
                if (!array_key_exists($email_new, $arr_data_report)) {
                    $data_report_2['date_start'] = $startDate->format('Y-m-d');
                    $data_report_2['date_end']   = $endDate->format('Y-m-d');
                }

                // Lưu lại
                $arr_data_report[$email_new] = $data_report_2;
            }
        }

        // Xử lý lưu vào DB
        $reportRunMarketingModel = \App::make('App\Models\ReportRunMarketing');
        $userGroupModel = \App::make('App\Models\UserGroup');
        $groupModel = \App::make('App\Models\Group');
        foreach ($arr_data_report as $key_email => $data_report) {

            $reportResult = $reportRunMarketingModel->where('date_start', $data_report['date_start'])
                                                      ->where('date_end', $data_report['date_end'])
                                                      ->where('user_email', $key_email)
                                                      ->where('type', ReportConstant::REPORT_RUN_MARKETING_TYPE_1)
                                                      ->first();
            if (is_null($reportResult)) {
                $userDB = $userModel->where('email', $key_email)->where('status', 1)->first();
                if (!is_null($userDB)) {
                    // Lưu thông tin user
                    $data_report['user_id']         = (int) $userDB->id;
                    $data_report['user_name']       = $userDB->name;
                    $data_report['user_email']      = $userDB->email;
                    $data_report['organization_id'] = $userDB->organization_id;

                    // Lưu thông tin phòng
                    $userGroup = $userGroupModel->where('user_id', (int)$userDB->id)
                                                ->orderBy('id', 'desc')
                                                ->first();
                    if (!is_null($userGroup)) {
                        $groupDB = $groupModel->where('id', $userGroup->group_id)->first();
                        if (!is_null($groupDB)) {
                            $data_report['group_id']   = (int) $groupDB->id;
                            $data_report['group_name'] = $groupDB->name;
                        }
                    }

                    // Lưu báo cáo
                    $reportResult =  $reportRunMarketingModel->create($data_report);
                }
            } else {
                // Update lại báo cáo đã có
                $data_report = $this->unsetDataEntry($data_report);
                $reportResult->fill($data_report)->save();
            }
        }

        // dump($reportResult);
        // dd("Đã đếm lại xong: " . date('Y-m-d H:i:s', time()));
    }

    public function getChanel($type)
    {
        $CB_ZALO           = 4;
        $CB_FACE_BOOK_CHAT = 2;
        $CB_LIVE_CHAT      = 1;

        // if ($type == $CB_LIVE_CHAT) {
        //     return 'livechat';
        // }
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
        if ($customer['via'] == ViaConstant::KEY_GOOGLE_FORM) {
            return 'gg_form';
        }
        if ($customer['via'] == ViaConstant::KEY_FACEBOOK) {
            return 'fb_form';
        }
        if ($customer['via'] == ViaConstant::KEY_FACEBOOK_ADS) {
            return 'facebook';
        }
        if ($customer['via'] == ViaConstant::KEY_ZALO) {
            return 'zalo';
        }
        // (Live chat + Hotline + Kh hệ thống + KH nhận ưu đãi)
        $arr_gg_system = [
            ViaConstant::KEY_GOOGLE_ADS,
            ViaConstant::KEY_GOOGLE,
            ViaConstant::KEY_TIKTOK,
            ViaConstant::KEY_OTHER,
            ViaConstant::KEY_RESEARCH,
        ];
        if (in_array($customer['via'], $arr_gg_system)) {
            return 'gg_system';
        }
        return null; // Không xác định
    }

    public function cronDataOne($data)
    {
        // Khởi tạo dữ liệu
        $data_report = [];
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được

            'need_reference_',       // Có nhu cầu - Tham khảo
            'need_order_',           // Có nhu cầu - Đã lên đơn
            'need_ordered_',         // Có nhu cầu - Đã đặt hàng

            'ordered_personal_',     // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_',     // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_',    // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_',    // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'gg_system',
            'gg_form',
            'zalo',
            'facebook',
            'fb_form',
        ];
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                $data_report[$type . $chanel] = 0;
            }
        }

        // Xử lý
        foreach ($data as $key => $item) {
            $chanelV2  = $this->getChanelHV($item);
            if (!is_null($chanelV2)) {
                $arr_labels = $item['labels'];
                if (count($arr_labels) > 0) {
                    foreach ($arr_labels as $key_label => $label_item) {
                        $label = $label_item['code'];
                        if ($label == Label::KHONG_TUONG_TAC_DUOC) {
                            $data_report['no_interaction_' . $chanelV2] += 1;
                        }
                        if ($label == Label::KHONG_CO_NHU_CAU) {
                            $data_report['no_need_' . $chanelV2] += 1;
                        }
                        if ($label == Label::MAT_HANG_KHONG_NHAP_DUOC) {
                            $data_report['no_enter_good_' . $chanelV2] += 1;
                        }

                        if ($label == Label::THAM_KHAO) {
                            $data_report['need_reference_' . $chanelV2] += 1;
                        }
                        if ($label == Label::ORDERED) {
                            $data_report['need_order_' . $chanelV2] += 1;
                        }
                        if ($label == Label::DA_DAT_HANG) {
                            $data_report['need_ordered_' . $chanelV2] += 1;
                        }

                        $businessType = $item['business_type'];
                        if (!is_null($businessType)) {
                            if ($label == Label::DA_DAT_HANG) {
                                $data_report['ordered_' . $businessType . '_' . $chanelV2] += 1;
                            }
                            if ($label == Label::CONSIGNMENT_ORDERED) {
                                $data_report['consignment_' . $businessType . '_' . $chanelV2] += 1;
                            }
                            if ($label == Label::CHUYEN_TIEN_QUOC_TE) {
                                $data_report['pay_help_' . $businessType . '_' . $chanelV2] += 1;
                            }
                        }
                    }
                }
            }
        }

        // Xóa reset data không được nhập
        $data_report = $this->resetDataEntry($data_report);

        return $data_report;
    }

    public function resetDataEntry($data_report)
    {
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được
        ];
        $arr_chanel = [
            'gg_form',
            'fb_form',
        ];
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                $data_report[$type . $chanel] = 0;
            }
        }

        return $data_report;
    }

    public function unsetDataEntry($data_report)
    {
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được
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

        return $data_report;
    }

    public function cronDataChatbiz($data, $data_report_1 = null)
    {
        // Khởi tạo dữ liệu
        $data_report = [];
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được

            'need_reference_',       // Có nhu cầu - Tham khảo
            'need_order_',           // Có nhu cầu - Đã lên đơn
            'need_ordered_',         // Có nhu cầu - Đã đặt hàng

            'ordered_personal_',     // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_',     // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_',    // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_',    // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'gg_system',
            'gg_form',
            'zalo',
            'facebook',
            'fb_form',
        ];
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                $data_report[$type . $chanel] = 0;
            }
        }

        // Nếu có truyền vào thì lấy luôn theo cái truyền
        if (!is_null($data_report_1)) {
            $data_report = $data_report_1;
        }

        // Các nhãn Chatbiz
        $CB_KHONG_TUONG_TAC_DUOC             = '899226054299c40274d766e71d781';
        $CB_KHONG_CO_NHU_CAU                 = '8992260542944518d5375ae5d6a73';
        $CB_KHONG_NHAP_DUOC                  = '899226054296083589804111e0af3';

        // Xử lý
        foreach ($data as $key => $item) {
            $chanel = $this->getChanel($item['type']);
            if (!is_null($chanel)) {
                $arr_labels = $item['labels'];
                if (count($arr_labels) > 0) {
                    foreach ($arr_labels as $key_label => $label_item) {
                        $label = $label_item['id'];
                        if ($label == $CB_KHONG_TUONG_TAC_DUOC) {
                            $data_report['no_interaction_' . $chanel] += 1;
                        }
                        if ($label == $CB_KHONG_CO_NHU_CAU) {
                            $data_report['no_need_' . $chanel] += 1;
                        }
                        if ($label == $CB_KHONG_NHAP_DUOC) {
                            $data_report['no_enter_good_' . $chanel] += 1;
                        }
                    }
                }
            }
        }

        // Xóa reset data không được nhập
        $data_report = $this->resetDataEntry($data_report);

        return $data_report;
    }
    public function mappingUser($email_chatbiz)
    {
        $arr_users = ChatbizConstant::ARR_MAP_USER_CHATBIZ_HANGVE;
        if (array_key_exists($email_chatbiz, $arr_users)) {
            return $arr_users[$email_chatbiz];
        }
        return $email_chatbiz;
    }

    public function getData($reportDBs)
    {
        // Khởi tạo dữ liệu
        $data_report = [];
        $arr_type = [
            'no_interaction_',       // Không tương tác được
            'no_need_',              // Không có nhu cầu
            'no_enter_good_',        // Không nhập được

            'need_reference_',       // Có nhu cầu - Tham khảo
            'need_order_',           // Có nhu cầu - Đã lên đơn
            'need_ordered_',         // Có nhu cầu - Đã đặt hàng

            'ordered_personal_',     // Đã đặt hàng - Khách tiêu dùng
            'ordered_business_',     // Đã đặt hàng - Khách kinh doanh

            'consignment_personal_', // Kí gửi - Khách tiêu dùng
            'consignment_business_', // Kí gửi - Khách kinh doanh

            'pay_help_personal_',    // Thanh toán hộ - Khách tiêu dùng
            'pay_help_business_',    // Thanh toán hộ - Khách kinh doanh
        ];
        $arr_chanel = [
            'gg_system',
            'gg_form',
            'zalo',
            'facebook',
            'fb_form',
        ];
        foreach ($arr_type as $key_1 => $type) {
            foreach ($arr_chanel as $key_2 => $chanel) {
                $data_report[$type . $chanel] = 0;
            }
        }

        foreach ($data_report as $key_1 => $value) {
            $sum = 0;
            foreach ($reportDBs as $key_2 => $item) {
                $sum += $item[$key_1];
            }
            $data_report[$key_1] = $sum;
        }

        // Tính toán thêm
        $resultDB = $data_report;

        // Không có nhu cầu
        $arr_type_no_need = [
            'no_need_', // Không có nhu cầu
            'no_enter_good_', // Có nhu cầu nhưng không nhập được
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

            if ($resultDB['total_customer_revice_sum'] == 0) {
                $resultDB[$type . 'sum_percent'] = 0;
            } else {
                $resultDB[$type . 'sum_percent'] = round($resultDB[$type . 'sum'] / $resultDB['total_customer_revice_sum'] * 100);
            }
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
        if ($resultDB['total_customer_revice_sum'] == 0) {
            $resultDB['total_customer_no_need_sum_percent'] = 0;
        } else {
            $resultDB['total_customer_no_need_sum_percent'] = round($resultDB['total_customer_no_need_sum'] / $resultDB['total_customer_revice_sum'] * 100);
        }

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
        return $resultDB;
    }

    // Lấy theo thời gian gán nhãn
    public function cronDataByTimeSignLabel()
    {
        $guzzle = new Guzzle;
        $url = 'http://172.18.0.14/get-data-customer-by-conversation-08012025-kmoqujr';
        $url = 'https://api-v2.chatbiz.io/get-data-customer-by-conversation-08012025-kmoqujr';

        $now       = Carbon::now();
        $startDate = Carbon::now()->startOfWeek();
        $endDate   = Carbon::now()->endOfWeek();

        // $data_report['date_start'] = '2024-12-02 00:00:00';
        // $data_report['date_end']   = '2024-12-08 23:59:59';

        $startDateCheck = $startDate->format('Y-m-d') . ' 00:00:00';
        $endDateCheck   = $endDate->format('Y-m-d') . ' 23:59:59';

        $userModel = \App::make('App\Models\User');
        $arr_data_report = []; // Tổng các thế thoại

        // Đếm trong nội bộ hệ thống
        $customerLabelModel = \App::make('App\Models\CustomerLabel');
        $customerLabelDBs = $customerLabelModel->where('created_at', '>=', $startDateCheck)
                                              ->where('created_at', '<=', $endDateCheck)
                                              ->get();

        $customer_ids = [];

        if (count($customerLabelDBs) > 0) {
            $customer_ids = \Arr::pluck($customerLabelDBs, 'customer_id');
        }

        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->whereIn('id', $customer_ids)
                                    ->where('status', 1)
                                    ->with('labels')
                                    ->get();

        $collection = collect($customerDBs->toArray());
        $grouped = $collection->groupBy('staff_group_id');
        $grouped->all();

        foreach ($grouped as $key_group_1 => $group) {
            $data_report = $this->cronDataOne($group);
            $data_report['date_start'] = $startDate->format('Y-m-d');
            $data_report['date_end']   = $endDate->format('Y-m-d');
            $userDB = $userModel->where('id', $key_group_1)->where('status', 1)->first();
            if (!is_null($userDB)) {
                $arr_data_report[$userDB->email] = $data_report;
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

        if ($result && $result['status_code'] == 200) {
            $collection = collect($result['data']);
            $grouped = $collection->groupBy('agent_email');
            $grouped->all();

            foreach ($grouped as $agent_email => $group) {
                // Lấy email tương ứng
                $email_new = $this->mappingUser($agent_email);

                // Kiểm tra xem có trùng không
                $data_report_1 = null;
                if (array_key_exists($email_new, $arr_data_report)) {
                    $data_report_1 = $arr_data_report[$email_new];
                }

                // Tính toán
                $data_report_2 = $this->cronDataChatbiz($group, $data_report_1);

                // Bổ sung thêm thời gian nếu không có email trong mảng gốc
                if (!array_key_exists($email_new, $arr_data_report)) {
                    $data_report_2['date_start'] = $startDate->format('Y-m-d');
                    $data_report_2['date_end']   = $endDate->format('Y-m-d');
                }

                // Lưu lại
                $arr_data_report[$email_new] = $data_report_2;
            }
        }

        // Xử lý lưu vào DB
        $reportRunMarketingModel = \App::make('App\Models\ReportRunMarketing');
        $userGroupModel = \App::make('App\Models\UserGroup');
        $groupModel = \App::make('App\Models\Group');
        foreach ($arr_data_report as $key_email => $data_report) {

            $reportResult = $reportRunMarketingModel->where('date_start', $data_report['date_start'])
                                                      ->where('date_end', $data_report['date_end'])
                                                      ->where('user_email', $key_email)
                                                      ->where('type', ReportConstant::REPORT_RUN_MARKETING_TYPE_2)
                                                      ->first();
            if (is_null($reportResult)) {
                $userDB = $userModel->where('email', $key_email)->where('status', 1)->first();
                if (!is_null($userDB)) {
                    // Lưu thông tin user
                    $data_report['user_id']         = (int) $userDB->id;
                    $data_report['user_name']       = $userDB->name;
                    $data_report['user_email']      = $userDB->email;
                    $data_report['organization_id'] = $userDB->organization_id;
                    $data_report['type']            = ReportConstant::REPORT_RUN_MARKETING_TYPE_2;

                    // Lưu thông tin phòng
                    $userGroup = $userGroupModel->where('user_id', (int)$userDB->id)
                                                ->orderBy('id', 'desc')
                                                ->first();
                    if (!is_null($userGroup)) {
                        $groupDB = $groupModel->where('id', $userGroup->group_id)->first();
                        if (!is_null($groupDB)) {
                            $data_report['group_id']   = (int) $groupDB->id;
                            $data_report['group_name'] = $groupDB->name;
                        }
                    }

                    // Lưu báo cáo
                    $reportResult =  $reportRunMarketingModel->create($data_report);
                }
            } else {
                // Update lại báo cáo đã có
                $data_report = $this->unsetDataEntry($data_report);
                $reportResult->fill($data_report)->save();
            }
        }

        // dump($reportResult);
        // dd("Đã đếm lại xong: " . date('Y-m-d H:i:s', time()));
    }

    // Lấy theo thời gian tạo tài khoản nhưng Realtime
    public function cronDataRealtime($params)
    {
        $guzzle = new Guzzle;
        $url = 'http://172.18.0.14/get-data-customer-by-conversation-08012025-kmoqujr';
        $url = 'https://api-v2.chatbiz.io/get-data-customer-by-conversation-08012025-kmoqujr';

        $now       = Carbon::now();
        $startDate = $params['date_start'];
        $endDate   = $params['date_end'];

        // $data_report['date_start'] = '2024-12-02 00:00:00';
        // $data_report['date_end']   = '2024-12-08 23:59:59';

        $startDateCheck = $startDate . ' 00:00:00';
        $endDateCheck   = $endDate . ' 23:59:59';


        $userModel = \App::make('App\Models\User');
        $arr_data_report = []; // Tổng các thế thoại

        // Đếm trong nội bộ hệ thống
        $customerModel = \App::make('App\Models\Customer');
        $customerDBs = $customerModel->where('created_at', '>=', $startDateCheck)
                                    ->where('created_at', '<=', $endDateCheck)
                                    ->where('status', 1)
                                    ->with('labels')
                                    ->get();;

        $collection = collect($customerDBs->toArray());
        $grouped = $collection->groupBy('staff_group_id');
        $grouped->all();

        foreach ($grouped as $key_group_1 => $group) {
            $data_report = $this->cronDataOne($group);
            $data_report['date_start'] = $startDate;
            $data_report['date_end']   = $endDate;
            $userDB = $userModel->where('id', $key_group_1)->where('status', 1)->first();
            if (!is_null($userDB)) {
                $arr_data_report[$userDB->email] = $data_report;
            }
        }

        // Đếm bên Chatbiz
        $options = [
            'query' => [
                "date_start" => $startDate,
                "date_end"   => $endDate
            ]
        ];

        $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);

        if ($result && $result['status_code'] == 200) {
            $collection = collect($result['data']);
            $grouped = $collection->groupBy('agent_email');
            $grouped->all();

            foreach ($grouped as $agent_email => $group) {
                // Lấy email tương ứng
                $email_new = $this->mappingUser($agent_email);

                // Kiểm tra xem có trùng không
                $data_report_1 = null;
                if (array_key_exists($email_new, $arr_data_report)) {
                    $data_report_1 = $arr_data_report[$email_new];
                }

                // Tính toán
                $data_report_2 = $this->cronDataChatbiz($group, $data_report_1);

                // Bổ sung thêm thời gian nếu không có email trong mảng gốc
                if (!array_key_exists($email_new, $arr_data_report)) {
                    $data_report_2['date_start'] = $startDate;
                    $data_report_2['date_end']   = $endDate;
                }

                // Lưu lại
                $arr_data_report[$email_new] = $data_report_2;
            }
        }


        // Chế cháo
        $arr_data_report_done = [];
        $reportRunMarketingModel = \App::make('App\Models\ReportRunMarketing');
        $userGroupModel = \App::make('App\Models\UserGroup');
        $groupModel = \App::make('App\Models\Group');
        foreach ($arr_data_report as $key_email => $data_report) {
            $userDB = $userModel->where('email', $key_email)->where('status', 1)->first();
            if (!is_null($userDB)) {
                // Lưu thông tin user
                $data_report['user_id']         = (int) $userDB->id;
                $data_report['user_name']       = $userDB->name;
                $data_report['user_email']      = $userDB->email;
                $data_report['organization_id'] = $userDB->organization_id;

                // Lưu thông tin phòng
                $userGroup = $userGroupModel->where('user_id', (int)$userDB->id)
                                            ->orderBy('id', 'desc')
                                            ->first();
                if (!is_null($userGroup)) {
                    $groupDB = $groupModel->where('id', $userGroup->group_id)->first();
                    if (!is_null($groupDB)) {
                        $data_report['group_id']   = (int) $groupDB->id;
                        $data_report['group_name'] = $groupDB->name;
                    }
                }

                // Cập nhật 6 cột nhập tay lại
                $reportResult = $reportRunMarketingModel->where('date_start', $data_report['date_start'])
                                                          ->where('date_end', $data_report['date_end'])
                                                          ->where('user_email', $key_email)
                                                          ->where('type', ReportConstant::REPORT_RUN_MARKETING_TYPE_1)
                                                          ->first();

                if (!is_null($reportResult)) {
                    $arr_type = [
                        'no_interaction_',       // Không tương tác được
                        'no_need_',              // Không có nhu cầu
                        'no_enter_good_',        // Không nhập được
                    ];
                    $arr_chanel = [
                        'gg_form',
                        'fb_form',
                    ];
                    foreach ($arr_type as $key_1 => $type) {
                        foreach ($arr_chanel as $key_2 => $chanel) {
                            $index100 = $type . $chanel;
                            $data_report[$index100] = $reportResult->$index100;
                        }
                    }

                    // hehehe
                }
            }

            array_push($arr_data_report_done, $data_report);
        }
        return $arr_data_report_done;
    }
}

