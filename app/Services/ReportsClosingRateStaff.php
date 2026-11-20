<?php

namespace App\Services;
use App\Constants\Report\ChatBiz;
use App\Helpers\OrderStatus;
use App\Models\Customer;
use App\Models\ReportClosingRateStaff;
use Illuminate\Support\Facades\Log;

class ReportsClosingRateStaff
{
    protected ReportService $service;
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public function cronjobInsertData($request): bool
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $mappingData = $this->mappingData(
            $calculatorDatetime['week_start_date'],
            $calculatorDatetime['week_end_date'],
            $list
        );
        $this->insert($mappingData);
        return true;
    }

    private function mappingData($startDate, $endDate, $list): array
    {
        $khachHangTheoNhanChatBiz = $this->khachHangTheoNhanChatBiz($startDate, $endDate);
        $khachHangTheoNhan = $this->khachHangTheoNhan(
            $startDate.' 00:00:00',
            $endDate.' 23:59:59'
        );
        if (sizeof($khachHangTheoNhanChatBiz) >= sizeof($khachHangTheoNhan)) {
            $firstArray = $khachHangTheoNhanChatBiz;
            $secondArray = $khachHangTheoNhan;
        } else {
            $firstArray = $khachHangTheoNhan;
            $secondArray = $khachHangTheoNhanChatBiz;
        }
        $mergeDataUnique = [];
        foreach ($firstArray as $keyFirst => $valueFirst) {
            if (isset($secondArray[$keyFirst])) {
                $mergeDataUnique[$keyFirst] = array_merge($valueFirst, $secondArray[$keyFirst]);
                unset($firstArray[$keyFirst]);
                unset($secondArray[$keyFirst]);
            }
        }

        $userUnique = [];
        foreach ($mergeDataUnique as $key => $value) {
            $userUnique[$key] = [
                'name' => $value['name'],
                'phone' => $value['phone'],
                'total_customer_personal_google' => 0,
                'total_customer_personal_facebook' => 0,
                'total_customer_business_google' => 0,
                'total_customer_business_facebook' => 0,

                'total_customer_not_immediately_google' => $this->uniquePhoneNumber(
                        $value['hangVeThamKhaoChuaCoNhuCauNgayGoogle'] ?? []
                        , []
                    )
                    + sizeof($value['hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone'] ?? []),

                'total_customer_not_immediately_facebook' => $this->uniquePhoneNumber(
                        $value['hangVeThamKhaoChuaCoNhuCauNgay'] ?? []
                        , []
                    )
                    + sizeof($value['hangVeThamKhaoChuaCoNhuCauNgayNotPhone'] ?? []),

                'total_customer_follow_facebook' => $this->uniquePhoneNumber(
                        $value['chatBizDangFollow'] ?? [],
                        $value['hangVeDangFollow'] ?? []
                    )
                    + sizeof($value['hangVeDangFollowNotPhone'] ?? [])
                    + sizeof($value['chatBizDangFollowNotPhone'] ?? []),

                'total_customer_follow_google' => $this->uniquePhoneNumber(
                        $value['hangVeDangFollowGoogle'] ?? [],
                        []
                    )
                    + sizeof($value['hangVeDangFollowGoogleNotPhone'] ?? []),

                'total_customer_reference_facebook' => $this->uniquePhoneNumber(
                        $value['hangVeThamKhao'] ?? [],
                        $value['chatBizThamKhao'] ?? []
                    )
                    + sizeof($value['hangVeThamKhaoNotPhone'] ?? [])
                    + sizeof($value['chatBizThamKhaoNotPhone'] ?? []),

                'total_customer_reference_google' => $this->uniquePhoneNumber(
                        $value['hangVeThamKhaoGoogle'] ?? [],
                        []
                    )
                    + sizeof($value['hangVeThamKhaoGoogleNotPhone'] ?? []),

                'total_customer_not_imported_facebook' => $this->uniquePhoneNumber(
                        $value['hangVeMatHangKhongNhapDuoc'] ?? [],
                        $value['chatBizKhongNhapDuoc'] ?? []
                    )
                    + sizeof($value['chatBizKhongNhapDuocNotPhone'] ?? [])
                    + sizeof($value['hangVeMatHangKhongNhapDuocNotPhone'] ?? []),

                'total_customer_not_imported_google' => $this->uniquePhoneNumber(
                        $value['hangVeMatHangKhongNhapDuocGoogle'] ?? [],
                        []
                    )
                    + sizeof($value['hangVeMatHangKhongNhapDuocGoogleNotPhone'] ?? []),

                'total_customer_not_need_facebook' => $this->uniquePhoneNumber(
                        $value['hangVeKhachHangKhongCoNhuCau'] ?? [],
                        $value['chatBizKhongCoNhuCau'] ?? []
                    )
                    + sizeof($value['hangVeKhachHangKhongCoNhuCauNotPhone'] ?? [])
                    + sizeof($value['chatBizKhongCoNhuCauNotPhone'] ?? []),

                'total_customer_not_need_google' => $this->uniquePhoneNumber(
                        $value['hangVeKhachHangKhongCoNhuCauGoogle'] ?? [],
                        []
                    )
                    + sizeof($value['hangVeKhachHangKhongCoNhuCauGoogleNotPhone'] ?? []),

                'total_customer_not_interact_facebook' => $this->uniquePhoneNumber(
                        $value['hangVeKhachHangKhongTuongTacDuoc'] ?? [],
                        $value['chatBizKhongTuongTacDuoc'] ?? []
                    )
                    + sizeof($value['hangVeKhachHangKhongTuongTacDuocNotPhone'] ?? [])
                    + sizeof($value['chatBizKhongTuongTacDuocNotPhone'] ?? []),

                'total_customer_not_interact_google' => $this->uniquePhoneNumber(
                        $value['hangVeKhachHangKhongTuongTacDuocGoogle'] ?? [],
                        []
                    )
                    + sizeof($value['hangVeKhachHangKhongTuongTacDuocGoogleNotPhone'] ?? []),
            ];
        }
        $userNotUnique = [];
        $mergeDataNotUnique = array_merge($firstArray, $secondArray);
        if (!empty($mergeDataNotUnique)) {
            foreach ($mergeDataNotUnique as $keyF => $valueF) {
                $userNotUnique[$keyF] = [
                    'name' => $valueF['name'],
                    'phone' => $valueF['phone'],
                    'total_customer_personal_google' => 0,
                    'total_customer_personal_facebook' => 0,
                    'total_customer_business_google' => 0,
                    'total_customer_business_facebook' => 0,
                    'total_customer_not_immediately_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeThamKhaoChuaCoNhuCauNgayGoogle'] ?? []
                            , []
                        )
                        + sizeof($valueF['hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone'] ?? []),

                    'total_customer_not_immediately_facebook' => $this->uniquePhoneNumber(
                            $value['hangVeThamKhaoChuaCoNhuCauNgay'] ?? []
                            , []
                        )
                        + sizeof($valueF['hangVeThamKhaoChuaCoNhuCauNgayNotPhone'] ?? []),

                    'total_customer_follow_facebook' => $this->uniquePhoneNumber(
                            $valueF['chatBizDangFollow'] ?? [],
                            $valueF['hangVeDangFollow'] ??[]
                        )
                        + sizeof($valueF['hangVeDangFollowNotPhone'] ?? [])
                        + sizeof($valueF['chatBizDangFollowNotPhone'] ?? []),

                    'total_customer_follow_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeDangFollowGoogle'] ?? [],
                            []
                        )
                        + sizeof($valueF['hangVeDangFollowGoogleNotPhone'] ?? []),

                    'total_customer_reference_facebook' => $this->uniquePhoneNumber(
                            $valueF['hangVeThamKhao'] ?? [],
                            $valueF['chatBizThamKhao'] ?? []
                        )
                        + sizeof($valueF['hangVeThamKhaoNotPhone'] ?? [])
                        + sizeof($valueF['chatBizThamKhaoNotPhone'] ?? []),

                    'total_customer_reference_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeThamKhaoGoogle'] ?? [],
                            []
                        )
                        + sizeof($valueF['hangVeThamKhaoGoogleNotPhone'] ?? []),

                    'total_customer_not_imported_facebook' => $this->uniquePhoneNumber(
                            $valueF['hangVeMatHangKhongNhapDuoc'] ?? [],
                            $valueF['chatBizKhongNhapDuoc'] ?? []
                        )
                        + sizeof($valueF['chatBizKhongNhapDuocNotPhone'] ?? [])
                        + sizeof($valueF['hangVeMatHangKhongNhapDuocNotPhone'] ?? []),

                    'total_customer_not_imported_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeMatHangKhongNhapDuocGoogle'] ?? [],
                            []
                        )
                        + sizeof($valueF['hangVeMatHangKhongNhapDuocGoogleNotPhone'] ?? []),

                    'total_customer_not_need_facebook' => $this->uniquePhoneNumber(
                            $valueF['hangVeKhachHangKhongCoNhuCau'] ?? [],
                            $valueF['chatBizKhongCoNhuCau'] ?? []
                        )
                        + sizeof($valueF['hangVeKhachHangKhongCoNhuCauNotPhone'] ?? [])
                        + sizeof($valueF['chatBizKhongCoNhuCauNotPhone'] ?? []),

                    'total_customer_not_need_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeKhachHangKhongCoNhuCauGoogle'] ?? [],
                            []
                        )
                        + sizeof($valueF['hangVeKhachHangKhongCoNhuCauGoogleNotPhone'] ?? []),

                    'total_customer_not_interact_facebook' => $this->uniquePhoneNumber(
                            $valueF['hangVeKhachHangKhongTuongTacDuoc'] ?? [],
                            $valueF['chatBizKhongTuongTacDuoc'] ?? []
                        )
                        + sizeof($valueF['hangVeKhachHangKhongTuongTacDuocNotPhone'] ?? [])
                        + sizeof($valueF['chatBizKhongTuongTacDuocNotPhone'] ?? []),

                    'total_customer_not_interact_google' => $this->uniquePhoneNumber(
                            $valueF['hangVeKhachHangKhongTuongTacDuocGoogle'] ?? [],
                            []
                        )
                        + sizeof($valueF['hangVeKhachHangKhongTuongTacDuocGoogleNotPhone'] ?? []),
                ];
            }
        }
        $userAll =  array_merge($userUnique, $userNotUnique);
        $userPhoneAll = array_keys($userAll);
        $khachHangKinhDoanhTieuDungHangVe = $this->khachHangKinhDoanhTieuDungHangVe(
            $startDate. ' 00:00:00',
            $endDate. ' 23:59:59',
            $userPhoneAll
        );
        foreach ($userAll as $key => &$uAll) {
            $uAll['created_at'] = $startDate. ' 00:00:00';
            $uAll['updated_at'] = $endDate. ' 23:59:59';
            $uAll['week'] = $list->week;
            $uAll['month'] = $list->month;
            $uAll['year'] = $list->year;
            if (isset($khachHangKinhDoanhTieuDungHangVe[$key])) {
                $uAll['total_customer_personal_google'] = sizeof($khachHangKinhDoanhTieuDungHangVe[$key]['total_customer_personal_google']);
                $uAll['total_customer_personal_facebook'] = sizeof($khachHangKinhDoanhTieuDungHangVe[$key]['total_customer_personal_facebook']);
                $uAll['total_customer_business_google'] = sizeof($khachHangKinhDoanhTieuDungHangVe[$key]['total_customer_business_google']);
                $uAll['total_customer_business_facebook'] = sizeof($khachHangKinhDoanhTieuDungHangVe[$key]['total_customer_business_facebook']);
            }
        }
        unset($uAll);
        return $userAll;
    }

    private function khachHangTheoNhanChatBiz($weekStartDate, $weekEndDate): array
    {
        $data = array("date_start" => $weekStartDate, "date_end" => $weekEndDate);
        $results =  $this->curl($data, ChatBiz::CHAT_BIZ_URI_ALL, "GET");
        $users = [];
        if (!empty($results->data)) {
            $acceptData = array_filter($results->data, function ($value){
                return in_array($value->type, [ChatBiz::CHAT_BIZ_FACE_BOOK_CHAT, ChatBiz::CHAT_BIZ_ZALO]);
            });
            $dataTuVan = [];
            foreach ($acceptData as $value) {
                if (!empty($value->agent_group_data) && $value->agent_group_data->id == ChatBiz::PHONG_TU_VAN) {
                    $dataTuVan[] = $value;
                }
            }
            foreach ($dataTuVan as $data) {
                if (!empty($data->agent_data)) {
                    $users[$data->agent_data->phone] = [
                        'chatBizKhongCoNhuCau' => [],
                        'chatBizKhongCoNhuCauNotPhone' => [],
                        'chatBizKhongNhapDuoc' => [],
                        'chatBizKhongNhapDuocNotPhone' => [],
                        'chatBizKhongTuongTacDuoc' => [],
                        'chatBizKhongTuongTacDuocNotPhone' => [],
                        'chatBizThamKhao' => [],
                        'chatBizThamKhaoNotPhone' => [],
                        'chatBizDangFollow' => [],
                        'chatBizDangFollowNotPhone' => [],
                        'name' => $data->agent_data->name,
                        'phone' => $data->agent_data->phone
                    ];
                }
            }
            foreach ($dataTuVan as $value) {
                if (!empty($value->labels)) {
                    foreach ($users as $key => &$user) {
                        if ($key == $value->agent_data->phone) {
                            foreach ($value->labels as $label) {
                                if ($label->id == ChatBiz::CHAT_BIZ_KHONG_CO_NHU_CAU) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizKhongCoNhuCau'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizKhongCoNhuCauNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KHONG_NHAP_DUOC) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizKhongNhapDuoc'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizKhongNhapDuocNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KHONG_TUONG_TAC_DUOC) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizKhongTuongTacDuoc'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizKhongTuongTacDuocNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KH_ORDER_THAM_KHAO) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizThamKhao'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizThamKhaoNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KHACH_HANG_KINH_DOANH) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizKinhDoanh'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizKinhDoanhNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KHACH_HANG_TIEU_DUNG) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizTieuDung'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizTieuDungNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }

                                if ($label->id == ChatBiz::CHAT_BIZ_KH_ORDER_FOLLOW) {
                                    if (!empty($value->phone_number)) {
                                        $user['chatBizDangFollow'][] = $value->phone_number;
                                    } else {
                                        $user['chatBizDangFollowNotPhone'][] = [
                                            'customerId' => $value->visitor_id,
                                            'social' => $value->social,
                                            'type' => $value->type,
                                            'phone_number' => '',
                                            'labelName' => $label->text,
                                            'labelId' => $label->id
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($user);
        return $users;
    }

    private function khachHangKinhDoanhTieuDungHangVe($weekStartDate, $weekEndDate, $userPhoneAll): array
    {
        $results = Customer::query()->select(
                'customers.id as customerId',
                'customers.name as customerName',
                'customers.via as social',
                'customers.phone_number as customerPhone',
                'users.id as userId',
                'users.phone_number as userPhone',
                'users.name as userName',
                'customers.business_type as businessType'
            )
            ->join('orders','orders.customer_id','=','customers.id')
            ->join('users','customers.staff_group_id','=','users.id')
            ->whereIn('customers.via', [
                ChatBiz::GOOGLE_ADS, ChatBiz::FACEBOOK_ADS, ChatBiz::GOOGLE, ChatBiz::ZALO])
            ->whereIn('customers.business_type', [ChatBiz::BUSINESS, ChatBiz::PERSONAL])
            ->whereNotNull('customers.staff_group_id')
            ->where('orders.status', OrderStatus::DAT_HANG)
            ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
            ->whereIn('users.phone_number',$userPhoneAll)
            ->get()->toArray();
        $users = [];
        if (!empty($results)) {
            foreach ($results as $value) {
                $users[$value['userPhone']] = [
                    'total_customer_personal_google' => [],
                    'total_customer_personal_facebook' => [],
                    'total_customer_business_google' => [],
                    'total_customer_business_facebook' => [],
                ];
            }
            foreach ($results as $value) {
                foreach ($users as $key => &$user) {
                    if ($key == $value['userPhone']) {
                        if (in_array($value['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            if ($value['businessType'] == ChatBiz::BUSINESS) {
                                $user['total_customer_business_facebook'][] = $value;
                            }
                            if ($value['businessType'] == ChatBiz::PERSONAL) {
                                $user['total_customer_personal_facebook'][] = $value;
                            }
                        }
                        if (in_array($value['social'], [ChatBiz::GOOGLE, ChatBiz::GOOGLE_ADS])) {
                            if ($value['businessType'] == ChatBiz::BUSINESS) {
                                $user['total_customer_business_google'][] = $value;
                            }
                            if ($value['businessType'] == ChatBiz::PERSONAL) {
                                $user['total_customer_personal_google'][] = $value;
                            }
                        }
                    }

                }
            }
        }
        return $users;
    }

    private function khachHangTheoNhan($weekStartDate, $weekEndDate): array
    {
        $query = Customer::query();
        $query->select(
            'customers.id as customerId',
            'customers.name as customerName',
            'customers.via as social',
            'customers.phone_number as customerPhone',
            'labels.name as labelName',
            'labels.code as labelCode',
            'users.id as userId',
            'users.phone_number as userPhone',
            'users.name as userName'
        );
        $query = $query->join('labels','customers.label_id','=','labels.id');
        $query = $query->join('users','customers.staff_group_id','=','users.id');
        $query = $query->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate]);
        $query = $query->whereNotNull('customers.staff_group_id');
        $query = $query->whereIn('customers.via',
            [ChatBiz::GOOGLE, ChatBiz::GOOGLE_ADS, ChatBiz::ZALO, ChatBiz::FACEBOOK_ADS]
        );
        $queryKhachHangKhongCoNhuCau = clone $query;
        $queryKhachHangThamKhaoChuaCoNhuCau = clone $query;
        $queryMatHangKhongNhapDuoc = clone $query;
        $queryKhachHangKhongTuongTacDuoc = clone $query;
        $queryKhachHangThamKhao = clone $query;
        $queryKhachHangDangFollow = clone $query;
        $results =  [
            ChatBiz::KHACH_KHONG_CO_NHU_CAU => $queryKhachHangKhongCoNhuCau
                ->where('labels.code',ChatBiz::KHACH_KHONG_CO_NHU_CAU)
                ->get()
                ->toArray(),
            ChatBiz::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY => $queryKhachHangThamKhaoChuaCoNhuCau
                ->where('labels.code',ChatBiz::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY)
                ->get()
                ->toArray(),
            ChatBiz::MAT_HANG_KHONG_NHAP_DUOC => $queryMatHangKhongNhapDuoc
                ->where('labels.code',ChatBiz::MAT_HANG_KHONG_NHAP_DUOC)
                ->get()
                ->toArray(),
            ChatBiz::KHONG_TUONG_TAC_DUOC => $queryKhachHangKhongTuongTacDuoc
                ->where('labels.code',ChatBiz::KHONG_TUONG_TAC_DUOC)
                ->get()
                ->toArray(),
            ChatBiz::THAM_KHAO => $queryKhachHangThamKhao
                ->where('labels.code',ChatBiz::THAM_KHAO)
                ->get()
                ->toArray(),
            ChatBiz::DANG_FOLLOW => $queryKhachHangDangFollow
                ->where('labels.code',ChatBiz::DANG_FOLLOW)
                ->get()
                ->toArray(),
        ];
        $resultFormat = collect(array_merge(...array_values($results)));
        $res = $resultFormat->pluck('userName','userPhone')->toArray();
        $userPhone = array_unique(array_keys($res));
        $users = [];
        foreach ($userPhone as $u) {
            $users[$u]['hangVeKhachHangKhongCoNhuCauGoogle'] = [];
            $users[$u]['hangVeKhachHangKhongCoNhuCauGoogleNotPhone'] = [];
            $users[$u]['hangVeKhachHangKhongCoNhuCau'] = [];
            $users[$u]['hangVeKhachHangKhongCoNhuCauNotPhone'] = [];

            $users[$u]['hangVeThamKhaoChuaCoNhuCauNgayGoogle'] = [];
            $users[$u]['hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone'] = [];
            $users[$u]['hangVeThamKhaoChuaCoNhuCauNgay'] = [];
            $users[$u]['hangVeThamKhaoChuaCoNhuCauNgayNotPhone'] = [];

            $users[$u]['hangVeKhachHangKhongTuongTacDuocGoogle'] = [];
            $users[$u]['hangVeKhachHangKhongTuongTacDuocGoogleNotPhone'] = [];
            $users[$u]['hangVeKhachHangKhongTuongTacDuoc'] = [];
            $users[$u]['hangVeKhachHangKhongTuongTacDuocNotPhone'] = [];

            $users[$u]['hangVeMatHangKhongNhapDuocGoogle'] = [];
            $users[$u]['hangVeMatHangKhongNhapDuocGoogleNotPhone'] = [];
            $users[$u]['hangVeMatHangKhongNhapDuoc'] = [];
            $users[$u]['hangVeMatHangKhongNhapDuocNotPhone'] = [];

            $users[$u]['hangVeThamKhaoGoogle'] = [];
            $users[$u]['hangVeThamKhaoGoogleNotPhone'] = [];
            $users[$u]['hangVeThamKhao'] = [];
            $users[$u]['hangVeThamKhaoNotPhone'] = [];

            $users[$u]['hangVeDangFollowGoogle'] = [];
            $users[$u]['hangVeDangFollowGoogleNotPhone'] = [];
            $users[$u]['hangVeDangFollow'] = [];
            $users[$u]['hangVeDangFollowNotPhone'] = [];
            $users[$u]['name'] = $res[$u] ?? '';
            $users[$u]['phone'] = $u;
        }
        foreach ($results as $key => $labels) {
            foreach ($users as $kUser => &$user) {
                if ($key == ChatBiz::KHACH_KHONG_CO_NHU_CAU) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeKhachHangKhongCoNhuCauGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeKhachHangKhongCoNhuCau'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeKhachHangKhongCoNhuCauGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeKhachHangKhongCoNhuCauNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }

                if ($key == ChatBiz::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeThamKhaoChuaCoNhuCauNgayGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeThamKhaoChuaCoNhuCauNgay'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeThamKhaoChuaCoNhuCauNgayNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }

                if ($key == ChatBiz::MAT_HANG_KHONG_NHAP_DUOC) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeMatHangKhongNhapDuocGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeMatHangKhongNhapDuoc'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeMatHangKhongNhapDuocGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeMatHangKhongNhapDuocNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }

                if ($key == ChatBiz::KHONG_TUONG_TAC_DUOC) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeKhachHangKhongTuongTacDuocGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeKhachHangKhongTuongTacDuoc'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeKhachHangKhongTuongTacDuocGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeKhachHangKhongTuongTacDuocNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }

                if ($key == ChatBiz::THAM_KHAO) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeThamKhaoGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeThamKhao'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeThamKhaoGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeThamKhaoNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }

                if ($key == ChatBiz::DANG_FOLLOW) {
                    foreach ($labels as $label) {
                        if ($kUser == $label['userPhone']) {
                            if (!empty($label['customerPhone'])) {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeDangFollowGoogle'][] = $label['customerPhone'];
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeDangFollow'][] = $label['customerPhone'];
                                }
                            } else {
                                if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                                    $user['hangVeDangFollowGoogleNotPhone'][] = $label;
                                }
                                if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                                    $user['hangVeDangFollowNotPhone'][] = $label;
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($user);
        return $users;
    }

    private function uniquePhoneNumber($arrayFirst, $arraySecond): int
    {
        if (empty($arrayFirst) && empty($arraySecond)) {
            return 0;
        }
        if (!empty($arrayFirst) && !empty($arraySecond)) {
            $merge = array_merge($arrayFirst, $arraySecond);
            return sizeof(array_unique($merge));
        }
        if (!empty($arrayFirst)) {
            return sizeof($arrayFirst);
        }
        if (!empty($arraySecond)) {
            return sizeof($arraySecond);
        }
        return 0;
    }

    private function curl($data, $url, $method){
        try {
            $data_string = json_encode($data);
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
            );
            $result = curl_exec($curl);
            curl_close($curl);
            Log::info('GET data from chatBiz ReportsClosingRate '. $data_string);
            return json_decode($result,false);
        } catch (\Throwable $e) {
            Log::info('GET data from chatBiz ReportsClosingRate Error : '.$e->getMessage());
            return false;
        }
    }

    public function insert($data){
        try {
            foreach ($data as $value) {
                ReportClosingRateStaff::insert($value);
            }
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportClosingRateStaff::where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->delete();
    }

    public function getReport($request): array
    {
        $results = ReportClosingRateStaff::where('week', $request['weekEnd'])
            ->where('month', $request['monthEnd'])
            ->where('year', $request['yearEnd'])
            ->get()->toArray();
        if (empty($results)) {
            return [];
        }
        $reports = [];
        foreach ($results as $value) {
            $reports[$value['phone']] = $value;
            /*Tổng google all*/
            $reports[$value['phone']]['total_google_all'] = 0;
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_not_interact_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_not_need_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_not_imported_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_not_immediately_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_reference_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_follow_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_personal_google'];
            $reports[$value['phone']]['total_google_all'] += $value['total_customer_business_google'];
            /*Tổng facebook all*/
            $reports[$value['phone']]['total_facebook_all'] = 0;
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_not_interact_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_not_need_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_not_imported_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_not_immediately_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_reference_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_follow_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_personal_facebook'];
            $reports[$value['phone']]['total_facebook_all'] += $value['total_customer_business_facebook'];
            /*Tổng google không có nhu cầu*/
            $reports[$value['phone']]['total_not_need_google_all'] = 0;
            $reports[$value['phone']]['total_not_need_google_all'] += $value['total_customer_not_interact_google'];
            $reports[$value['phone']]['total_not_need_google_all'] += $value['total_customer_not_need_google'];
            $reports[$value['phone']]['total_not_need_google_all'] += $value['total_customer_not_imported_google'];
            /*Tổng facebook không có nhu cầu*/
            $reports[$value['phone']]['total_not_need_facebook_all'] = 0;
            $reports[$value['phone']]['total_not_need_facebook_all'] += $value['total_customer_not_interact_facebook'];
            $reports[$value['phone']]['total_not_need_facebook_all'] += $value['total_customer_not_need_facebook'];
            $reports[$value['phone']]['total_not_need_facebook_all'] += $value['total_customer_not_imported_facebook'];
            /*Tổng google có nhu cầu*/
            $reports[$value['phone']]['total_need_google_all'] = 0;
            $reports[$value['phone']]['total_need_google_all'] += $value['total_customer_follow_google'];
            $reports[$value['phone']]['total_need_google_all'] += $value['total_customer_not_immediately_google'];
            $reports[$value['phone']]['total_need_google_all'] += $value['total_customer_reference_google'];
            $reports[$value['phone']]['total_need_google_all'] += $value['total_customer_personal_google'];
            $reports[$value['phone']]['total_need_google_all'] += $value['total_customer_business_google'];
            /*Tổng facebook có nhu cầu*/
            $reports[$value['phone']]['total_need_facebook_all'] = 0;
            $reports[$value['phone']]['total_need_facebook_all'] += $value['total_customer_follow_facebook'];
            $reports[$value['phone']]['total_need_facebook_all'] += $value['total_customer_not_immediately_facebook'];
            $reports[$value['phone']]['total_need_facebook_all'] += $value['total_customer_reference_facebook'];
            $reports[$value['phone']]['total_need_facebook_all'] += $value['total_customer_personal_facebook'];
            $reports[$value['phone']]['total_need_facebook_all'] += $value['total_customer_business_facebook'];
            /*Tỷ lệ tổng google không có nhu cầu*/
            $reports[$value['phone']]['percent_not_need_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_not_need_google_all']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google không tương tác được*/
            $reports[$value['phone']]['percent_not_interact_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_interact_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google không có nhu cầu*/
            $reports[$value['phone']]['percent_not_need_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_need_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google có nhu cầu nhưng không nhập được*/
            $reports[$value['phone']]['percent_not_imported_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_imported_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;

            /*Tỷ lệ tổng facebook không có nhu cầu*/
            $reports[$value['phone']]['percent_not_need_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_not_need_facebook_all']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook không tương tác được*/
            $reports[$value['phone']]['percent_not_interact_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_interact_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook không có nhu cầu*/
            $reports[$value['phone']]['percent_not_need_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_need_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook có nhu cầu nhưng không nhập được*/
            $reports[$value['phone']]['percent_not_imported_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_imported_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;

            /*Tỷ lệ tổng google có nhu cầu*/
            $reports[$value['phone']]['percent_need_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_need_google_all']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google tham khảo chưa có nhu cầu ngay*/
            $reports[$value['phone']]['percent_not_immediately_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_immediately_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google tham khảo*/
            $reports[$value['phone']]['percent_reference_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_reference_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /*Tỷ lệ google đang follow*/
            $reports[$value['phone']]['percent_follow_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_follow_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;

            /*Tỷ lệ tổng facebook có nhu cầu*/
            $reports[$value['phone']]['percent_need_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_need_facebook_all']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook tham khảo chưa có nhu cầu ngay*/
            $reports[$value['phone']]['percent_not_immediately_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_immediately_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook tham khảo*/
            $reports[$value['phone']]['percent_reference_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_reference_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ facebook đang follow*/
            $reports[$value['phone']]['percent_follow_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_follow_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;

            /* Tỷ lệ google tiêu dùng*/
            $reports[$value['phone']]['percent_personal_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_personal_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /* Tỷ lệ facebook tiêu dùng*/
            $reports[$value['phone']]['percent_personal_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_personal_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;

            /* Tỷ lệ google kinh doanh*/
            $reports[$value['phone']]['percent_business_google'] = $reports[$value['phone']]['total_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_business_google']*100)/$reports[$value['phone']]['total_google_all'], 2
            ) : 0;
            /* Tỷ lệ facebook kinh doanh*/
            $reports[$value['phone']]['percent_business_facebook'] = $reports[$value['phone']]['total_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_business_facebook']*100)/$reports[$value['phone']]['total_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ CNC Tham khảo chưa có NC ngay*/

            /*Tỷ lệ CNC google tham khảo chưa có nhu cầu ngay*/
            $reports[$value['phone']]['percent_not_immediately_cnc_google'] = $reports[$value['phone']]['total_need_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_immediately_google']*100)/$reports[$value['phone']]['total_need_google_all'], 2
            ) : 0;
            /*Tỷ lệ CNC google tham khảo*/
            $reports[$value['phone']]['percent_reference_cnc_google'] = $reports[$value['phone']]['total_need_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_reference_google']*100)/$reports[$value['phone']]['total_need_google_all'], 2
            ) : 0;
            /*Tỷ lệ CNC google đang follow*/
            $reports[$value['phone']]['percent_follow_cnc_google'] = $reports[$value['phone']]['total_need_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_follow_google']*100)/$reports[$value['phone']]['total_need_google_all'], 2
            ) : 0;
            /* Tỷ lệ CNC google tiêu dùng*/
            $reports[$value['phone']]['percent_personal_cnc_google'] = $reports[$value['phone']]['total_need_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_personal_google']*100)/$reports[$value['phone']]['total_need_google_all'], 2
            ) : 0;
            /* Tỷ lệ CNC google kinh doanh*/
            $reports[$value['phone']]['percent_business_cnc_google'] = $reports[$value['phone']]['total_need_google_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_business_google']*100)/$reports[$value['phone']]['total_need_google_all'], 2
            ) : 0;

            /*Tỷ lệ CNC facebook tham khảo chưa có nhu cầu ngay*/
            $reports[$value['phone']]['percent_not_immediately_cnc_facebook'] = $reports[$value['phone']]['total_need_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_not_immediately_facebook']*100)/$reports[$value['phone']]['total_need_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ CNC facebook tham khảo*/
            $reports[$value['phone']]['percent_reference_cnc_facebook'] = $reports[$value['phone']]['total_need_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_reference_facebook']*100)/$reports[$value['phone']]['total_need_facebook_all'], 2
            ) : 0;
            /*Tỷ lệ CNC facebook đang follow*/
            $reports[$value['phone']]['percent_follow_cnc_facebook'] = $reports[$value['phone']]['total_need_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_follow_facebook']*100)/$reports[$value['phone']]['total_need_facebook_all'], 2
            ) : 0;
            /* Tỷ lệ CNC facebook tiêu dùng*/
            $reports[$value['phone']]['percent_personal_cnc_facebook'] = $reports[$value['phone']]['total_need_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_personal_facebook']*100)/$reports[$value['phone']]['total_need_facebook_all'], 2
            ) : 0;
            /* Tỷ lệ CNC facebook kinh doanh*/
            $reports[$value['phone']]['percent_business_cnc_facebook'] = $reports[$value['phone']]['total_need_facebook_all'] > 0 ? round(
                ($reports[$value['phone']]['total_customer_business_facebook']*100)/$reports[$value['phone']]['total_need_facebook_all'], 2
            ) : 0;

            /*Tỷ lệ/CNC google*/
            $reports[$value['phone']]['percent_cnc_need_google_all'] = 0;
            $reports[$value['phone']]['percent_cnc_need_google_all'] += $reports[$value['phone']]['percent_business_cnc_google'];
            $reports[$value['phone']]['percent_cnc_need_google_all'] += $reports[$value['phone']]['percent_personal_cnc_google'];
            $reports[$value['phone']]['percent_cnc_need_google_all'] += $reports[$value['phone']]['percent_follow_cnc_google'];
            $reports[$value['phone']]['percent_cnc_need_google_all'] += $reports[$value['phone']]['percent_reference_cnc_google'];
            $reports[$value['phone']]['percent_cnc_need_google_all'] += $reports[$value['phone']]['percent_not_immediately_cnc_google'];
            $reports[$value['phone']]['percent_cnc_need_google_all'] = round($reports[$value['phone']]['percent_cnc_need_google_all']);
            /*Tỷ lệ/CNC facebook*/
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] = 0;
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] += $reports[$value['phone']]['percent_business_cnc_facebook'];
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] += $reports[$value['phone']]['percent_personal_cnc_facebook'];
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] += $reports[$value['phone']]['percent_follow_cnc_facebook'];
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] += $reports[$value['phone']]['percent_reference_cnc_facebook'];
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] += $reports[$value['phone']]['percent_not_immediately_cnc_facebook'];
            $reports[$value['phone']]['percent_cnc_need_facebook_all'] = round($reports[$value['phone']]['percent_cnc_need_facebook_all']);
        }
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($request['yearEnd'], $request['monthEnd'], $request['weekEnd']);
        $time = [
            'start_date' => date('d-m-Y',strtotime($calculatorDatetime['week_start_date'])),
            'end_date' =>  date('d-m-Y',strtotime($calculatorDatetime['week_end_date'])),
        ];
        $reports = array_values($reports);
        $data['format'] = ($reports);
        $data['time'] = $time;
        $data['count'] = sizeof($reports);
        return  $data;
    }
}
