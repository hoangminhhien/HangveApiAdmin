<?php

namespace App\Services;

use App\Constants\Report\ChatBiz;
use App\Helpers\OrderStatus;
use App\Models\Customer;
use App\Models\ReportClosingRate;
use Illuminate\Support\Facades\Log;

class ReportsClosingRate
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
        $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $mappingData = $this->mappingData(
            $calculatorDatetime['week_start_date'],
            $calculatorDatetime['week_end_date']
        );
        $mappingData['created_at'] = $weekStartDate;
        $mappingData['updated_at'] = $weekEndDate;
        $mappingData['week'] = $list->week;
        $mappingData['month'] = $list->month;
        $mappingData['year'] = $list->year;
        $this->insert($mappingData);
        return true;
    }
    private function mappingData($startDate, $endDate): array
    {
        $khachHangTheoNhanChatBiz = $this->khachHangTheoNhanChatBiz($startDate, $endDate);
        $khachHangKinhDoanhTieuDungHangVeCursor = $this->khachHangKinhDoanhTieuDungHangVe(
            $startDate. ' 00:00:00',
            $endDate. ' 23:59:59'
        );
        $khachHangKinhDoanhTieuDungHangVe = [];
        foreach ($khachHangKinhDoanhTieuDungHangVeCursor as $cursor) {
            $khachHangKinhDoanhTieuDungHangVe[] = $cursor->toArray();
        }
        $mappingKhachHangKinhDoanhTieuDung = $this->mappingKhachHangKinhDoanhTieuDung(
            $khachHangTheoNhanChatBiz,
            $khachHangKinhDoanhTieuDungHangVe
        );
        $khachHangTheoNhan = $this->khachHangTheoNhan(
            $startDate.' 00:00:00',
            $endDate.' 23:59:59'
        );
        $mappingKhachHangKhongCoNhuCau = $this->mappingKhachHangKhongCoNhuCau(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        $mappingKhachHangThamKhaoChuaCoNhuCau = $this->mappingKhachHangThamKhaoChuaCoNhuCau($khachHangTheoNhan);
        $mappingKhachHangCoNhuCauNhungKhongNhapDuoc = $this->mappingKhachHangCoNhuCauNhungKhongNhapDuoc(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        $mappingKhachHangThamKhao = $this->mappingKhachHangThamKhao(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        $mappingKhachHangFollow = $this->mappingKhachHangFollow(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        $mappingKhachHangKhongTuongTacDuoc = $this->mappingKhachHangKhongTuongTacDuoc(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );

        $totalGoogle = 0;
        $totalGoogle += $mappingKhachHangCoNhuCauNhungKhongNhapDuoc['totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle'];
        $totalGoogle += $mappingKhachHangKhongTuongTacDuoc['totalKhachHangKhongTuongTacDuocGoogle'];
        $totalGoogle += $mappingKhachHangFollow['totalKhachHangDangFollowGoogle'];
        $totalGoogle += $mappingKhachHangThamKhao['totalKhachHangThamKhaoGoogle'];
        $totalGoogle += $mappingKhachHangThamKhaoChuaCoNhuCau['totalKhachHangThamKhaoChuaCoNhuCauGoogle'];
        $totalGoogle += $mappingKhachHangKhongCoNhuCau['totalKhachHangKhongCoNhuCauGoogle'];
        $totalGoogle += $mappingKhachHangKinhDoanhTieuDung['totalKinhDoanhGoogle'];
        $totalGoogle += $mappingKhachHangKinhDoanhTieuDung['totalTieuDungGoogle'];

        $totalFacebook = 0;
        $totalFacebook += $mappingKhachHangCoNhuCauNhungKhongNhapDuoc['totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo'];
        $totalFacebook += $mappingKhachHangKhongTuongTacDuoc['totalKhachHangKhongTuongTacDuocFacebookZalo'];
        $totalFacebook += $mappingKhachHangFollow['totalKhachHangDangFollowFacebookZalo'];
        $totalFacebook += $mappingKhachHangThamKhao['totalKhachHangThamKhaoFacebookZalo'];
        $totalFacebook += $mappingKhachHangThamKhaoChuaCoNhuCau['totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo'];
        $totalFacebook += $mappingKhachHangKhongCoNhuCau['totalKhachHangKhongCoNhuCauFacebookZalo'];
        $totalFacebook += $mappingKhachHangKinhDoanhTieuDung['totalKinhDoanhFacebookZalo'];
        $totalFacebook += $mappingKhachHangKinhDoanhTieuDung['totalTieuDungFacebookZalo'];

        return
        [
            'total_all_customer_google' => $totalGoogle,
            'total_all_customer_facebook' => $totalFacebook,

            'total_customer_not_interact_google' => $mappingKhachHangKhongTuongTacDuoc['totalKhachHangKhongTuongTacDuocGoogle'],
            'total_customer_not_interact_facebook' => $mappingKhachHangKhongTuongTacDuoc['totalKhachHangKhongTuongTacDuocFacebookZalo'],

            'total_customer_not_need_google' => $mappingKhachHangKhongCoNhuCau['totalKhachHangKhongCoNhuCauGoogle'],
            'total_customer_not_need_facebook' => $mappingKhachHangKhongCoNhuCau['totalKhachHangKhongCoNhuCauFacebookZalo'],

            'total_customer_not_imported_google' => $mappingKhachHangCoNhuCauNhungKhongNhapDuoc['totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle'],
            'total_customer_not_imported_facebook' => $mappingKhachHangCoNhuCauNhungKhongNhapDuoc['totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo'],

            'total_customer_reference_google' => $mappingKhachHangThamKhao['totalKhachHangThamKhaoGoogle'],
            'total_customer_reference_facebook' => $mappingKhachHangThamKhao['totalKhachHangThamKhaoFacebookZalo'],

            'total_customer_follow_google' => $mappingKhachHangFollow['totalKhachHangDangFollowGoogle'],
            'total_customer_follow_facebook' => $mappingKhachHangFollow['totalKhachHangDangFollowFacebookZalo'],

            'total_customer_not_immediately_google' => $mappingKhachHangThamKhaoChuaCoNhuCau['totalKhachHangThamKhaoChuaCoNhuCauGoogle'],
            'total_customer_not_immediately_facebook' => $mappingKhachHangThamKhaoChuaCoNhuCau['totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo'],

            'total_customer_business_google' => $mappingKhachHangKinhDoanhTieuDung['totalKinhDoanhGoogle'],
            'total_customer_business_facebook' => $mappingKhachHangKinhDoanhTieuDung['totalKinhDoanhFacebookZalo'],

            'total_customer_personal_google' => $mappingKhachHangKinhDoanhTieuDung['totalTieuDungGoogle'],
            'total_customer_personal_facebook' => $mappingKhachHangKinhDoanhTieuDung['totalTieuDungFacebookZalo']
        ];
    }

    private function mappingKhachHangKhongCoNhuCau(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        $chatBizKhongCoNhuCau = $khachHangTheoNhanChatBiz['chatBizKhongCoNhuCau'];
        $chatBizKhongCoNhuCauNotPhone = $khachHangTheoNhanChatBiz['chatBizKhongCoNhuCauNotPhone'];
        $hangVeKhachHangKhongCoNhuCau = $khachHangTheoNhan['hangVeKhachHangKhongCoNhuCau'];
        $hangVeKhachHangKhongCoNhuCauNotPhone = $khachHangTheoNhan['hangVeKhachHangKhongCoNhuCauNotPhone'];
        $hangVeKhachHangKhongCoNhuCauGoogle = $khachHangTheoNhan['hangVeKhachHangKhongCoNhuCauGoogle'];
        $hangVeKhachHangKhongCoNhuCauGoogleNotPhone = $khachHangTheoNhan['hangVeKhachHangKhongCoNhuCauGoogleNotPhone'];

        $totalKhachHangKhongCoNhuCauFacebookZalo = $this->uniquePhoneNumber(
                $hangVeKhachHangKhongCoNhuCau,
                $chatBizKhongCoNhuCau
            )
            + sizeof($hangVeKhachHangKhongCoNhuCauNotPhone)
            + sizeof($chatBizKhongCoNhuCauNotPhone);
        $totalKhachHangKhongCoNhuCauGoogle = sizeof($hangVeKhachHangKhongCoNhuCauGoogle)
            + sizeof($hangVeKhachHangKhongCoNhuCauGoogleNotPhone);
        return [
            'totalKhachHangKhongCoNhuCauFacebookZalo' => $totalKhachHangKhongCoNhuCauFacebookZalo,
            'totalKhachHangKhongCoNhuCauGoogle' => $totalKhachHangKhongCoNhuCauGoogle,
        ];
    }


    private function khachHangKinhDoanhTieuDungHangVe($weekStartDate, $weekEndDate): \Illuminate\Support\LazyCollection
    {
        $sql =  Customer::query()->select(
            'customers.id as customerId',
            'customers.phone_number as customerPhone',
            'customers.via as social',
            'customers.business_type as businessType',
            'customers.service as customerService'
        )
        ->join('orders','orders.customer_id','=','customers.id')
        ->whereIn('customers.via', [ChatBiz::GOOGLE_ADS, ChatBiz::FACEBOOK_ADS, ChatBiz::GOOGLE, ChatBiz::ZALO])
        ->whereIn('customers.business_type', [ChatBiz::BUSINESS, ChatBiz::PERSONAL])
        ->whereNotNull('customers.staff_group_id')
        ->where('orders.status', OrderStatus::DAT_HANG)
        ->whereBetween('orders.date_ordered',[$weekStartDate, $weekEndDate])
        ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
        ;
        return $sql->cursor();
        /*dd($sql->toSql(), $sql->getBindings());*/
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
            'labels.code as labelCode'
        );
        $query = $query->join('labels','customers.label_id','=','labels.id');
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
        $hangVeKhachHangKhongCoNhuCauGoogle = [];
        $hangVeKhachHangKhongCoNhuCauGoogleNotPhone = [];
        $hangVeKhachHangKhongCoNhuCau = [];
        $hangVeKhachHangKhongCoNhuCauNotPhone = [];

        $hangVeThamKhaoChuaCoNhuCauNgayGoogle = [];
        $hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone = [];
        $hangVeThamKhaoChuaCoNhuCauNgay = [];
        $hangVeThamKhaoChuaCoNhuCauNgayNotPhone = [];

        $hangVeKhachHangKhongTuongTacDuocGoogle = [];
        $hangVeKhachHangKhongTuongTacDuocGoogleNotPhone = [];
        $hangVeKhachHangKhongTuongTacDuoc = [];
        $hangVeKhachHangKhongTuongTacDuocNotPhone = [];

        $hangVeMatHangKhongNhapDuocGoogle = [];
        $hangVeMatHangKhongNhapDuocGoogleNotPhone = [];
        $hangVeMatHangKhongNhapDuoc = [];
        $hangVeMatHangKhongNhapDuocNotPhone = [];

        $hangVeThamKhaoGoogle = [];
        $hangVeThamKhaoGoogleNotPhone = [];
        $hangVeThamKhao = [];
        $hangVeThamKhaoNotPhone = [];

        $hangVeDangFollowGoogle = [];
        $hangVeDangFollowGoogleNotPhone = [];
        $hangVeDangFollow = [];
        $hangVeDangFollowNotPhone = [];

        foreach ($results as $key => $labels) {
            if ($key == ChatBiz::KHACH_KHONG_CO_NHU_CAU) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeKhachHangKhongCoNhuCauGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeKhachHangKhongCoNhuCau[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeKhachHangKhongCoNhuCauGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeKhachHangKhongCoNhuCauNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == ChatBiz::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeThamKhaoChuaCoNhuCauNgay[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == ChatBiz::MAT_HANG_KHONG_NHAP_DUOC) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeMatHangKhongNhapDuocGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeMatHangKhongNhapDuoc[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeMatHangKhongNhapDuocGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeMatHangKhongNhapDuocNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == ChatBiz::KHONG_TUONG_TAC_DUOC) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeKhachHangKhongTuongTacDuocGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeKhachHangKhongTuongTacDuoc[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeKhachHangKhongTuongTacDuocGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeKhachHangKhongTuongTacDuocNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == ChatBiz::THAM_KHAO) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeThamKhaoGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeThamKhao[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeThamKhaoGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeThamKhaoNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == ChatBiz::DANG_FOLLOW) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeDangFollowGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeDangFollow[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [ChatBiz::GOOGLE_ADS, ChatBiz::GOOGLE])) {
                            $hangVeDangFollowGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                            $hangVeDangFollowNotPhone[] = $label;
                        }
                    }
                }
            }
        }
        return [
            'hangVeKhachHangKhongCoNhuCauGoogle' => $hangVeKhachHangKhongCoNhuCauGoogle,
            'hangVeKhachHangKhongCoNhuCauGoogleNotPhone' => $hangVeKhachHangKhongCoNhuCauGoogleNotPhone,
            'hangVeKhachHangKhongCoNhuCau' => $hangVeKhachHangKhongCoNhuCau,
            'hangVeKhachHangKhongCoNhuCauNotPhone' => $hangVeKhachHangKhongCoNhuCauNotPhone,

            'hangVeThamKhaoChuaCoNhuCauNgayGoogle' => $hangVeThamKhaoChuaCoNhuCauNgayGoogle,
            'hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone' => $hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone,
            'hangVeThamKhaoChuaCoNhuCauNgay' => $hangVeThamKhaoChuaCoNhuCauNgay,
            'hangVeThamKhaoChuaCoNhuCauNgayNotPhone' => $hangVeThamKhaoChuaCoNhuCauNgayNotPhone,

            'hangVeKhachHangKhongTuongTacDuocGoogle' => $hangVeKhachHangKhongTuongTacDuocGoogle,
            'hangVeKhachHangKhongTuongTacDuocGoogleNotPhone' => $hangVeKhachHangKhongTuongTacDuocGoogleNotPhone,
            'hangVeKhachHangKhongTuongTacDuoc' => $hangVeKhachHangKhongTuongTacDuoc,
            'hangVeKhachHangKhongTuongTacDuocNotPhone' => $hangVeKhachHangKhongTuongTacDuocNotPhone,

            'hangVeMatHangKhongNhapDuocGoogle' => $hangVeMatHangKhongNhapDuocGoogle,
            'hangVeMatHangKhongNhapDuocGoogleNotPhone' => $hangVeMatHangKhongNhapDuocGoogleNotPhone,
            'hangVeMatHangKhongNhapDuoc' => $hangVeMatHangKhongNhapDuoc,
            'hangVeMatHangKhongNhapDuocNotPhone' => $hangVeMatHangKhongNhapDuocNotPhone,

            'hangVeKhachHangThamKhaoGoogle' => $hangVeThamKhaoGoogle,
            'hangVeKhachHangThamKhaoGoogleNotPhone' => $hangVeThamKhaoGoogleNotPhone,
            'hangVeKhachHangThamKhao' => $hangVeThamKhao,
            'hangVeKhachHangThamKhaoNotPhone' => $hangVeThamKhaoNotPhone,

            'hangVeDangFollowGoogle' => $hangVeDangFollowGoogle,
            'hangVeDangFollowGoogleNotPhone' => $hangVeDangFollowGoogleNotPhone,
            'hangVeDangFollow' => $hangVeDangFollow,
            'hangVeDangFollowNotPhone' => $hangVeDangFollowNotPhone,
        ];
    }

    private function khachHangTheoNhanChatBiz($weekStartDate, $weekEndDate): array
    {
        $data = array("date_start" => $weekStartDate, "date_end" => $weekEndDate);
        $results =  $this->curl($data, ChatBiz::CHAT_BIZ_URI_ALL, "GET");

        $chatBizKhongCoNhuCau = [];
        $chatBizKhongCoNhuCauNotPhone = [];

        $chatBizKhongNhapDuoc = [];
        $chatBizKhongNhapDuocNotPhone = [];

        $chatBizKhongTuongTacDuoc = [];
        $chatBizKhongTuongTacDuocNotPhone = [];

        $chatBizThamKhao = [];
        $chatBizThamKhaoNotPhone = [];

        $chatBizKinhDoanh = [];
        $chatBizKinhDoanhNotPhone = [];

        $chatBizTieuDung = [];
        $chatBizTieuDungNotPhone = [];

        $chatBizDangFollow = [];
        $chatBizDangFollowNotPhone = [];

        if (!empty($results->data)) {
            $acceptData = array_filter($results->data, function ($value){
                return in_array($value->type, [ChatBiz::CHAT_BIZ_FACE_BOOK_CHAT, ChatBiz::CHAT_BIZ_ZALO]);
            });
            foreach ($acceptData as $value) {
                if (!empty($value->labels)) {
                    foreach ($value->labels as $label) {
                        if ($label->id == ChatBiz::CHAT_BIZ_KHONG_CO_NHU_CAU) {
                            if (!empty($value->phone_number)) {
                                $chatBizKhongCoNhuCau[] = $value->phone_number;
                            } else {
                                $chatBizKhongCoNhuCauNotPhone[] = [
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
                                $chatBizKhongNhapDuoc[] = $value->phone_number;
                            } else {
                                $chatBizKhongNhapDuocNotPhone[] = [
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
                                $chatBizKhongTuongTacDuoc[] = $value->phone_number;
                            } else {
                                $chatBizKhongTuongTacDuocNotPhone[] = [
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
                                $chatBizThamKhao[] = $value->phone_number;
                            } else {
                                $chatBizThamKhaoNotPhone[] = [
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
                                $chatBizKinhDoanh[] = $value->phone_number;
                            } else {
                                $chatBizKinhDoanhNotPhone[] = [
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
                                $chatBizTieuDung[] = $value->phone_number;
                            } else {
                                $chatBizTieuDungNotPhone[] = [
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
                                $chatBizDangFollow[] = $value->phone_number;
                            } else {
                                $chatBizDangFollowNotPhone[] = [
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
        return [
            'chatBizKhongCoNhuCau' => $chatBizKhongCoNhuCau,
            'chatBizKhongCoNhuCauNotPhone' => $chatBizKhongCoNhuCauNotPhone,
            'chatBizKhongNhapDuoc' => $chatBizKhongNhapDuoc,
            'chatBizKhongNhapDuocNotPhone' => $chatBizKhongNhapDuocNotPhone,
            'chatBizKhongTuongTacDuoc' => $chatBizKhongTuongTacDuoc,
            'chatBizKhongTuongTacDuocNotPhone' => $chatBizKhongTuongTacDuocNotPhone,
            'chatBizThamKhao' => $chatBizThamKhao,
            'chatBizThamKhaoNotPhone' => $chatBizThamKhaoNotPhone,
            'chatBizKinhDoanh' => $chatBizKinhDoanh,
            'chatBizKinhDoanhNotPhone' => $chatBizKinhDoanhNotPhone,
            'chatBizTieuDung' => $chatBizTieuDung,
            'chatBizTieuDungNotPhone' => $chatBizTieuDungNotPhone,
            'chatBizDangFollow' => $chatBizDangFollow,
            'chatBizDangFollowNotPhone' => $chatBizDangFollowNotPhone,
        ];
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

    public function getReport($request)
    {
        $results = ReportClosingRate::query()->where('week', $request['weekEnd'])
            ->where('month', $request['monthEnd'])
            ->where('year', $request['yearEnd'])
            ->first();
        if (empty($results)) {
            return [];
        }
        $total_all_customer_google = $results->total_all_customer_google;
        $total_all_customer_facebook = $results->total_all_customer_facebook;

        /* khách hàng không tương tác được*/
        $percent_total_customer_not_interact_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_not_interact_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_not_interact_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_not_interact_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /*Khách hàng không có nhu cầu*/
        $percent_total_customer_not_need_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_not_need_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_not_need_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_not_need_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng không nhập được */
        $percent_total_customer_not_imported_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_not_imported_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_not_imported_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_not_imported_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng không có nhu cầu ngay lập tức*/
        $percent_total_customer_not_immediately_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_not_immediately_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_not_immediately_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_not_immediately_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng không tương tác được*/
        $percent_total_customer_reference_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_reference_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_reference_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_reference_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng follow*/
        $percent_total_customer_follow_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_follow_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_follow_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_follow_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng tieu dung*/
        $percent_total_customer_personal_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_personal_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_personal_facebook = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_personal_facebook*100)/$total_all_customer_facebook, 2) : 0;

        /* Khách hàng kinh doanh*/
        $percent_total_customer_business_google = $total_all_customer_google > 0 ? round(
            ($results->total_customer_business_google*100)/$total_all_customer_google, 2) : 0;

        $percent_total_customer_business_facebook  = $total_all_customer_facebook > 0 ? round(
            ($results->total_customer_business_facebook*100)/$total_all_customer_facebook, 2) : 0;

        $percent_total_all_customer_google = 0;
        $percent_total_all_customer_google += $percent_total_customer_not_interact_google;
        $percent_total_all_customer_google += $percent_total_customer_not_need_google;
        $percent_total_all_customer_google += $percent_total_customer_not_imported_google;
        $percent_total_all_customer_google += $percent_total_customer_not_immediately_google;
        $percent_total_all_customer_google += $percent_total_customer_reference_google;
        $percent_total_all_customer_google += $percent_total_customer_follow_google;
        $percent_total_all_customer_google += $percent_total_customer_personal_google;
        $percent_total_all_customer_google += $percent_total_customer_business_google;

        $percent_total_all_customer_facebook = 0;
        $percent_total_all_customer_facebook += $percent_total_customer_not_interact_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_not_need_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_not_imported_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_not_immediately_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_reference_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_follow_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_personal_facebook;
        $percent_total_all_customer_facebook += $percent_total_customer_business_facebook;

        $results->percent_total_all_customer_facebook = round($percent_total_all_customer_facebook);
        $results->percent_total_all_customer_google = round($percent_total_all_customer_google);

        $results->percent_total_customer_not_interact_google = $percent_total_customer_not_interact_google;
        $results->percent_total_customer_not_need_google = $percent_total_customer_not_need_google;
        $results->percent_total_customer_not_imported_google = $percent_total_customer_not_imported_google;
        $results->percent_total_customer_not_immediately_google = $percent_total_customer_not_immediately_google;
        $results->percent_total_customer_reference_google = $percent_total_customer_reference_google;
        $results->percent_total_customer_follow_google = $percent_total_customer_follow_google;
        $results->percent_total_customer_personal_google = $percent_total_customer_personal_google;
        $results->percent_total_customer_business_google = $percent_total_customer_business_google;

        $results->percent_total_customer_not_interact_facebook = $percent_total_customer_not_interact_facebook;
        $results->percent_total_customer_not_need_facebook = $percent_total_customer_not_need_facebook;
        $results->percent_total_customer_not_imported_facebook = $percent_total_customer_not_imported_facebook;
        $results->percent_total_customer_not_immediately_facebook = $percent_total_customer_not_immediately_facebook;
        $results->percent_total_customer_reference_facebook = $percent_total_customer_reference_facebook;
        $results->percent_total_customer_follow_facebook = $percent_total_customer_follow_facebook;
        $results->percent_total_customer_personal_facebook = $percent_total_customer_personal_facebook;
        $results->percent_total_customer_business_facebook = $percent_total_customer_business_facebook;
        $results->start_date = date('d-m-Y', strtotime($results->created_at));
        $results->end_date = date('d-m-Y', strtotime($results->updated_at));

        return $results;
    }

    public function insert($data){
        try {
            ReportClosingRate::query()->insert($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportClosingRate::where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->delete();
    }

    private function mappingKhachHangThamKhaoChuaCoNhuCau(array $khachHangTheoNhan): array
    {
        /*extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");*/
        $hangVeThamKhaoChuaCoNhuCauNgay = $khachHangTheoNhan['hangVeThamKhaoChuaCoNhuCauNgay'];
        $hangVeThamKhaoChuaCoNhuCauNgayNotPhone = $khachHangTheoNhan['hangVeThamKhaoChuaCoNhuCauNgayNotPhone'];
        $hangVeThamKhaoChuaCoNhuCauNgayGoogle = $khachHangTheoNhan['hangVeThamKhaoChuaCoNhuCauNgayGoogle'];
        $hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone = $khachHangTheoNhan['hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone'];
        $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo = sizeof($hangVeThamKhaoChuaCoNhuCauNgay)
            + sizeof($hangVeThamKhaoChuaCoNhuCauNgayNotPhone);
        $totalKhachHangThamKhaoChuaCoNhuCauGoogle = sizeof($hangVeThamKhaoChuaCoNhuCauNgayGoogle)
            + sizeof($hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone);
        return [
            'totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo' => $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo,
            'totalKhachHangThamKhaoChuaCoNhuCauGoogle' => $totalKhachHangThamKhaoChuaCoNhuCauGoogle,
        ];
    }

    private function mappingKhachHangCoNhuCauNhungKhongNhapDuoc(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        /*extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");*/

        $hangVeMatHangKhongNhapDuoc = $khachHangTheoNhan['hangVeMatHangKhongNhapDuoc'];
        $hangVeMatHangKhongNhapDuocNotPhone = $khachHangTheoNhan['hangVeMatHangKhongNhapDuocNotPhone'];
        $hangVeMatHangKhongNhapDuocGoogle = $khachHangTheoNhan['hangVeMatHangKhongNhapDuocGoogle'];
        $hangVeMatHangKhongNhapDuocGoogleNotPhone = $khachHangTheoNhan['hangVeMatHangKhongNhapDuocGoogleNotPhone'];
        $chatBizKhongNhapDuoc = $khachHangTheoNhanChatBiz['chatBizKhongNhapDuoc'];
        $chatBizKhongNhapDuocNotPhone = $khachHangTheoNhanChatBiz['chatBizKhongNhapDuocNotPhone'];

        $totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo = $this->uniquePhoneNumber(
                $hangVeMatHangKhongNhapDuoc,
                $chatBizKhongNhapDuoc
            )
            + sizeof($hangVeMatHangKhongNhapDuocNotPhone)
            + sizeof($chatBizKhongNhapDuocNotPhone);
        $totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle = sizeof($hangVeMatHangKhongNhapDuocGoogle)
            + sizeof($hangVeMatHangKhongNhapDuocGoogleNotPhone);
        return [
            'totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo' => $totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo,
            'totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle' => $totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle,
        ];
    }

    private function mappingKhachHangThamKhao(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        $hangVeThamKhaoGoogle = $khachHangTheoNhan['hangVeKhachHangThamKhaoGoogle'];
        $hangVeThamKhaoGoogleNotPhone = $khachHangTheoNhan['hangVeKhachHangThamKhaoGoogleNotPhone'];
        $hangVeThamKhao = $khachHangTheoNhan['hangVeKhachHangThamKhao'];
        $hangVeThamKhaoNotPhone = $khachHangTheoNhan['hangVeKhachHangThamKhaoNotPhone'];

        $chatBizThamKhao = $khachHangTheoNhanChatBiz['chatBizThamKhao'];
        $chatBizThamKhaoNotPhone = $khachHangTheoNhanChatBiz['chatBizThamKhaoNotPhone'];
        $totalKhachHangThamKhaoFacebookZalo = $this->uniquePhoneNumber(
                $hangVeThamKhao,
                $chatBizThamKhao
            )
            + sizeof($hangVeThamKhaoNotPhone)
            + sizeof($chatBizThamKhaoNotPhone);
        $totalKhachHangThamKhaoGoogle = $this->uniquePhoneNumber(
                $hangVeThamKhaoGoogle,
                []
            )
            + sizeof($hangVeThamKhaoGoogleNotPhone);
        return [
            'totalKhachHangThamKhaoFacebookZalo' => $totalKhachHangThamKhaoFacebookZalo,
            'totalKhachHangThamKhaoGoogle' => $totalKhachHangThamKhaoGoogle,
        ];
    }

    private function mappingKhachHangKinhDoanhTieuDung(array $khachHangTheoNhanChatBiz, array $khachHangKinhDoanhTieuDungHangVe): array
    {
        $hangVeKinhDoanh = [];
        $hangVeTieuDung = [];
        $hangVeKinhDoanhNotPhone = [];
        $hangVeTieuDungNotPhone = [];
        $hangVeKinhDoanhGoogle = [];
        $hangVeTieuDungGoogle = [];
        $chatBizKinhDoanh = $khachHangTheoNhanChatBiz['chatBizKinhDoanh'];
        $chatBizKinhDoanhNotPhone = $khachHangTheoNhanChatBiz['chatBizKinhDoanhNotPhone'];
        $chatBizTieuDung = $khachHangTheoNhanChatBiz['chatBizTieuDung'];
        $chatBizTieuDungNotPhone = $khachHangTheoNhanChatBiz['chatBizTieuDungNotPhone'];
        foreach ($khachHangKinhDoanhTieuDungHangVe as $value) {
            if (in_array($value['social'], [ChatBiz::FACEBOOK_ADS, ChatBiz::ZALO])) {
                if (!empty($value['customerPhone'])) {
                    if ($value['businessType'] == ChatBiz::BUSINESS) {
                        $hangVeKinhDoanh[] = $value['customerPhone'];
                    }
                    if ($value['businessType'] == ChatBiz::PERSONAL) {
                        $hangVeTieuDung[] = $value['customerPhone'];
                    }
                } else {
                    if ($value['businessType'] == ChatBiz::BUSINESS) {
                        $hangVeKinhDoanhNotPhone[] = $value;
                    }
                    if ($value['businessType'] == ChatBiz::PERSONAL) {
                        $hangVeTieuDungNotPhone[] = $value;
                    }
                }
            }
            if (in_array($value['social'], [ChatBiz::GOOGLE, ChatBiz::GOOGLE_ADS])) {
                if ($value['businessType'] == ChatBiz::BUSINESS) {
                    $hangVeKinhDoanhGoogle[] = $value;
                }
                if ($value['businessType'] == ChatBiz::PERSONAL) {
                    $hangVeTieuDungGoogle[] = $value;
                }
            }
        }
        $totalKinhDoanhFacebookZalo = $this->uniquePhoneNumber([], $hangVeKinhDoanh)
            + sizeof($hangVeKinhDoanhNotPhone);
        $totalKinhDoanhGoogle = sizeof($hangVeKinhDoanhGoogle);

        $totalTieuDungFacebookZalo = $this->uniquePhoneNumber([], $hangVeTieuDung)
            + sizeof($hangVeTieuDungNotPhone);
        $totalTieuDungGoogle = sizeof($hangVeTieuDungGoogle);
        return [
            'totalKinhDoanhFacebookZalo' => $totalKinhDoanhFacebookZalo,
            'totalKinhDoanhGoogle' => $totalKinhDoanhGoogle,
            'totalTieuDungFacebookZalo' => $totalTieuDungFacebookZalo,
            'totalTieuDungGoogle' => $totalTieuDungGoogle,
        ];
    }

    private function mappingKhachHangFollow(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        $hangVeDangFollowGoogle = $khachHangTheoNhan['hangVeDangFollowGoogle'];
        $hangVeDangFollowGoogleNotPhone = $khachHangTheoNhan['hangVeDangFollowGoogleNotPhone'];
        $hangVeDangFollow = $khachHangTheoNhan['hangVeDangFollow'];
        $hangVeDangFollowNotPhone = $khachHangTheoNhan['hangVeDangFollowNotPhone'];

        $chatBizDangFollow = $khachHangTheoNhanChatBiz['chatBizDangFollow'];
        $chatBizDangFollowNotPhone = $khachHangTheoNhanChatBiz['chatBizDangFollowNotPhone'];
        $totalKhachHangDangFollowFacebookZalo = $this->uniquePhoneNumber(
                $hangVeDangFollow,
                $chatBizDangFollow
            )
            + sizeof($hangVeDangFollowNotPhone)
            + sizeof($chatBizDangFollowNotPhone);
        $totalKhachHangDangFollowGoogle = $this->uniquePhoneNumber(
                $hangVeDangFollowGoogle,
                []
            )
            + sizeof($hangVeDangFollowGoogleNotPhone);
        return [
            'totalKhachHangDangFollowFacebookZalo' => $totalKhachHangDangFollowFacebookZalo,
            'totalKhachHangDangFollowGoogle' => $totalKhachHangDangFollowGoogle,
        ];
    }

    private function mappingKhachHangKhongTuongTacDuoc(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        $hangVeKhachHangKhongTuongTacDuoc = $khachHangTheoNhan['hangVeKhachHangKhongTuongTacDuoc'];
        $hangVeKhachHangKhongTuongTacDuocNotPhone = $khachHangTheoNhan['hangVeKhachHangKhongTuongTacDuocNotPhone'];
        $hangVeKhachHangKhongTuongTacDuocGoogle = $khachHangTheoNhan['hangVeKhachHangKhongTuongTacDuocGoogle'];
        $hangVeKhachHangKhongTuongTacDuocGoogleNotPhone = $khachHangTheoNhan['hangVeKhachHangKhongTuongTacDuocGoogleNotPhone'];

        $chatBizKhongTuongTacDuoc = $khachHangTheoNhanChatBiz['chatBizKhongTuongTacDuoc'];
        $chatBizKhongTuongTacDuocNotPhone = $khachHangTheoNhanChatBiz['chatBizKhongTuongTacDuocNotPhone'];

        $totalKhachHangKhongTuongTacDuocFacebookZalo = $this->uniquePhoneNumber(
                $hangVeKhachHangKhongTuongTacDuoc,
                $chatBizKhongTuongTacDuoc
            )
            + sizeof($hangVeKhachHangKhongTuongTacDuocNotPhone)
            + sizeof($chatBizKhongTuongTacDuocNotPhone);
        $totalKhachHangKhongTuongTacDuocGoogle = $this->uniquePhoneNumber(
                $hangVeKhachHangKhongTuongTacDuocGoogle,
                []
            )
            + sizeof($hangVeKhachHangKhongTuongTacDuocGoogleNotPhone);
        return [
            'totalKhachHangKhongTuongTacDuocFacebookZalo' => $totalKhachHangKhongTuongTacDuocFacebookZalo,
            'totalKhachHangKhongTuongTacDuocGoogle' => $totalKhachHangKhongTuongTacDuocGoogle,
        ];
    }
}
