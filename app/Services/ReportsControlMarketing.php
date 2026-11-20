<?php

namespace App\Services;
use App\Helpers\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Reports\ReportControlMarketingStart;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ReportControlMarketing;
class ReportsControlMarketing
{
    /* Social hang ve*/
    const GOOGLE_ADS = 'google_ads';
    const GOOGLE = 'google';
    const FACEBOOK_ADS = 'facebook_ads';
    const ZALO = 'zalo';
    /* business type hang ve*/
    const PERSONAL = 'personal';
    const BUSINESS = 'business';
    /* labels hang ve*/
    const KHONG_TUONG_TAC_DUOC = 'KHONG_TUONG_TAC_DUOC';
    const KHACH_KHONG_CO_NHU_CAU = 'KHACH_KHONG_CO_NHU_CAU';
    const THAM_KHAO_CHUA_CO_NHU_CAU_NGAY = 'THAM_KHAO_CHUA_CO_NHU_CAU_NGAY';
    const MAT_HANG_KHONG_NHAP_DUOC = 'MAT_HANG_KHONG_NHAP_DUOC';
    const DANG_FOLLOW = 'DANG_FOLLOW';
    const DA_LEN_DON = 'DA_LEN_DON';
    const KH_KI_GUI = 'KH_KI_GUI';
    const HEN_GOI_LAI = 'HEN_GOI_LAI';
    const KH_TU_TIM_KIEM = 'KH_TU_TIM_KIEM';
    const THONG_TIEN = 'THONG_TIEN';
    const KHACH_HANG_GOI_KHONG_NGHE_MAY = 'KHACH_HANG_GOI_KHONG_NGHE_MAY';
    const CONSIGNMENT = 1;
    const ORDER = 0;
    const CHAT_BIZ_URI = 'https://api-v2.chatbiz.io/get-data-customer-927sj217j1239x129i388a';

    /* Labels chat biz*/
    const CHAT_BIZ_KH_ORDER_THAM_KHAO = '8992260542805911ca27bec6f5fd5';
    const CHAT_BIZ_KHONG_CO_NHU_CAU = '8992260542944518d5375ae5d6a73';
    const CHAT_BIZ_KHONG_NHAP_DUOC = '899226054296083589804111e0af3';
    const CHAT_BIZ_KHONG_TUONG_TAC_DUOC = '899226054299c40274d766e71d781';
    const CHAT_BIZ_KHACH_HANG_KINH_DOANH = '8992260542a0bbfb4be55894b21b4';
    const CHAT_BIZ_KHACH_HANG_TIEU_DUNG = '8992260542d95ca1ce447c0341c72';
    const CHAT_BIZ_KHACH_HANG_DA_LEN_DON = '899226055c63f518d5375ae5d6d83';
    const CHAT_BIZ_KH_ORDER_FOLLOW = '899226055c665a02c28411c728897';
    const CHAT_BIZ_KH_TU_VAN_KY_GUI = '899226055c77a518d5375ae5d6d8c';
    const CHAT_BIZ_KH_KINH_DOANH = '899226055c87ec862cc2e5c779f33';
    const CHAT_BIZ_KH_TIEU_DUNG = '899226055c905a02c28411c7288aa';
    const CHAT_BIZ_KH_THANH_TOAN_HO = '8992260cdb8cf3ee06f6a5a56b912';
   /* Social chat biz*/
    const CHAT_BIZ_ZALO = 4;
    const CHAT_BIZ_FACE_BOOK_CHAT = 2;
    const CHAT_BIZ_LIVE_CHAT = 1;
    protected ReportService $service;
    function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public function cronjobInsertData($request): array
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getDayOfWeekOfMonth($list->year, $list->month, $list->week);
        $weekStartDate = $calculatorDatetime['week_start_date'] . ' 00:00:00';
        $weekEndDate = $calculatorDatetime['week_end_date'] . ' 23:59:59';
        $khachHangDatHang = $this->khachHangDatHang($weekStartDate, $weekEndDate);
        extract($khachHangDatHang, EXTR_PREFIX_SAME, "wddx");
        $khachHangFollow = $this->khachHangFollow($weekStartDate, $weekEndDate);
        extract($khachHangFollow, EXTR_PREFIX_SAME, "wddx");
        $dataMapping = $this->filterDataCustomerWithStatus($calculatorDatetime['week_start_date'], $calculatorDatetime['week_end_date']);
        extract($dataMapping, EXTR_PREFIX_SAME, "wddx");
        return [
            'total_customer_business_facebook' => $totalKinhDoanhFacebookZalo,
            'total_customer_business_google' => $totalKinhDoanhGoogle,
            'total_customer_personal_facebook' => $totalTieuDungFacebookZalo,
            'total_customer_personal_google' => $totalTieuDungGoogle,
            'total_customer_consignment_advise_facebook' => $totalKyGuiFacebookZalo,
            'total_customer_consignment_advise_google' => $totalKyGuiGoogle,
            'total_customer_not_need_facebook' => $totalKhachHangKhongCoNhuCauFacebookZalo,
            'total_customer_not_need_google' => $totalKhachHangKhongCoNhuCauGoogle,
            'total_customer_not_immediate_need_facebook' => $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo,
            'total_customer_not_immediate_need_google' => $totalKhachHangThamKhaoChuaCoNhuCauGoogle,
            'total_customer_need_but_item_not_imported_facebook' => $totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo,
            'total_customer_need_but_item_not_imported_google' => $totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle,
            'total_customer_order_business_google' => $khachHangDatHangKinhDoanhGoogle,
            'total_customer_order_business_facebook' => $khachHangDatHangKinhDoanhFacebookZalo,
            'total_customer_order_personal_facebook' => $khachHangDatHangTieuDungFacebookZalo,
            'total_customer_order_personal_google' => $khachHangDatHangTieuDungGoogle,
            'total_customer_order_follow_google' => $khachHangFollowGoogle,
            'total_customer_order_follow_facebook' => $khachHangFollowFacebookZalo,
            'total_customer_texted_not_respond' => $totalKhachHangNhanTinKhongPhanHoiFacebookZalo,
            'total_customer_calling_not_answer_google' => $hangVeKhachHangGoiKhongNgheMayGoogle,
            'total_customer_calling_not_answer_facebook' => $hangVeKhachHangGoiKhongNgheMay,
            'week' => $list->week,
            'month' => $list->month,
            'year' => $list->year,
            'type' => 'week',
            'created_at' => $weekStartDate,
            'updated_at' => $weekEndDate
        ];
    }

    public function cronjobInsertDataMonth($request): array
    {
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        $calculatorDatetime = CalculatorWeekReport::getStartAndEndDateOfMonth($request);
        $fistDayOfMonth = $calculatorDatetime->fistDayOfMonth . ' 00:00:00';
        $lastDayOfMonth = $calculatorDatetime->lastDayOfMonth . ' 23:59:59';
        $khachHangDatHang = $this->khachHangDatHang($fistDayOfMonth, $lastDayOfMonth);
        extract($khachHangDatHang, EXTR_PREFIX_SAME, "wddx");
        $khachHangFollow = $this->khachHangFollow($fistDayOfMonth, $lastDayOfMonth);
        extract($khachHangFollow, EXTR_PREFIX_SAME, "wddx");
        $dataMapping = $this->filterDataCustomerWithStatus(
            $calculatorDatetime->fistDayOfMonth, $calculatorDatetime->lastDayOfMonth
        );
        extract($dataMapping, EXTR_PREFIX_SAME, "wddx");
        return [
            'total_customer_business_facebook' => $totalKinhDoanhFacebookZalo,
            'total_customer_business_google' => $totalKinhDoanhGoogle,
            'total_customer_personal_facebook' => $totalTieuDungFacebookZalo,
            'total_customer_personal_google' => $totalTieuDungGoogle,
            'total_customer_consignment_advise_facebook' => $totalKyGuiFacebookZalo,
            'total_customer_consignment_advise_google' => $totalKyGuiGoogle,
            'total_customer_not_need_facebook' => $totalKhachHangKhongCoNhuCauFacebookZalo,
            'total_customer_not_need_google' => $totalKhachHangKhongCoNhuCauGoogle,
            'total_customer_not_immediate_need_facebook' => $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo,
            'total_customer_not_immediate_need_google' => $totalKhachHangThamKhaoChuaCoNhuCauGoogle,
            'total_customer_need_but_item_not_imported_facebook' => $totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo,
            'total_customer_need_but_item_not_imported_google' => $totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle,
            'total_customer_order_business_google' => $khachHangDatHangKinhDoanhGoogle,
            'total_customer_order_business_facebook' => $khachHangDatHangKinhDoanhFacebookZalo,
            'total_customer_order_personal_facebook' => $khachHangDatHangTieuDungFacebookZalo,
            'total_customer_order_personal_google' => $khachHangDatHangTieuDungGoogle,
            'total_customer_order_follow_google' => $khachHangFollowGoogle,
            'total_customer_order_follow_facebook' => $khachHangFollowFacebookZalo,
            'total_customer_texted_not_respond' => $totalKhachHangNhanTinKhongPhanHoiFacebookZalo,
            'total_customer_calling_not_answer_google' => $hangVeKhachHangGoiKhongNgheMayGoogle,
            'total_customer_calling_not_answer_facebook' => $hangVeKhachHangGoiKhongNgheMay,
            'week' => 0,
            'month' => $list->month,
            'year' => $list->year,
            'type' => 'month',
            'created_at' => $fistDayOfMonth,
            'updated_at' => $lastDayOfMonth
        ];
    }
    /*
        lấy khách hàng không có nhu cầu,
        KH Tham khảo chưa có nhu cầu ngay,
        KH có nhu cầu nhưng mặt hàng không nhập đươc
        KH gọi khong nghe máy
    */
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
        $query = $query->whereIn('customers.via',[self::GOOGLE, self::GOOGLE_ADS, self::ZALO, self::FACEBOOK_ADS]);
        $queryKhachHangKhongCoNhuCau = clone $query;
        $queryKhachHangThamKhaoChuaCoNhuCau = clone $query;
        $queryMatHangKhongNhapDuoc = clone $query;
        $queryKhachHangGoiKhongNgheMay = clone $query;
        $results =  [
            self::KHACH_KHONG_CO_NHU_CAU => $queryKhachHangKhongCoNhuCau
                ->where('labels.code',self::KHACH_KHONG_CO_NHU_CAU)
                ->get()
                ->toArray(),
            self::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY => $queryKhachHangThamKhaoChuaCoNhuCau
                ->where('labels.code',self::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY)
                ->get()
                ->toArray(),
            self::MAT_HANG_KHONG_NHAP_DUOC => $queryMatHangKhongNhapDuoc
                ->where('labels.code',self::MAT_HANG_KHONG_NHAP_DUOC)
                ->get()
                ->toArray(),
            self::KHACH_HANG_GOI_KHONG_NGHE_MAY => $queryKhachHangGoiKhongNgheMay
                ->where('labels.code',self::KHACH_HANG_GOI_KHONG_NGHE_MAY)
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

        $hangVeKhachHangGoiKhongNgheMayGoogle = [];
        $hangVeKhachHangGoiKhongNgheMayGoogleNotPhone = [];
        $hangVeKhachHangGoiKhongNgheMay = [];
        $hangVeKhachHangGoiKhongNgheMayNotPhone = [];

        $hangVeMatHangKhongNhapDuocGoogle = [];
        $hangVeMatHangKhongNhapDuocGoogleNotPhone = [];
        $hangVeMatHangKhongNhapDuoc = [];
        $hangVeMatHangKhongNhapDuocNotPhone = [];

        foreach ($results as $key => $labels) {
            if ($key == self::KHACH_KHONG_CO_NHU_CAU) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeKhachHangKhongCoNhuCauGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeKhachHangKhongCoNhuCau[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeKhachHangKhongCoNhuCauGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeKhachHangKhongCoNhuCauNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == self::THAM_KHAO_CHUA_CO_NHU_CAU_NGAY) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeThamKhaoChuaCoNhuCauNgay[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeThamKhaoChuaCoNhuCauNgayNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == self::MAT_HANG_KHONG_NHAP_DUOC) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeMatHangKhongNhapDuocGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeMatHangKhongNhapDuoc[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeMatHangKhongNhapDuocGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeMatHangKhongNhapDuocNotPhone[] = $label;
                        }
                    }
                }
            }

            if ($key == self::KHACH_HANG_GOI_KHONG_NGHE_MAY) {
                foreach ($labels as $label) {
                    if (!empty($label['customerPhone'])) {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeKhachHangGoiKhongNgheMayGoogle[] = $label['customerPhone'];
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeKhachHangGoiKhongNgheMay[] = $label['customerPhone'];
                        }
                    } else {
                        if (in_array($label['social'], [self::GOOGLE_ADS, self::GOOGLE])) {
                            $hangVeKhachHangGoiKhongNgheMayGoogleNotPhone[] = $label;
                        }
                        if (in_array($label['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                            $hangVeKhachHangGoiKhongNgheMayNotPhone[] = $label;
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

            'hangVeKhachHangGoiKhongNgheMayGoogle' => $hangVeKhachHangGoiKhongNgheMayGoogle,
            'hangVeKhachHangGoiKhongNgheMayGoogleNotPhone' => $hangVeKhachHangGoiKhongNgheMayGoogleNotPhone,
            'hangVeKhachHangGoiKhongNgheMay' => $hangVeKhachHangGoiKhongNgheMay,
            'hangVeKhachHangGoiKhongNgheMayNotPhone' => $hangVeKhachHangGoiKhongNgheMayNotPhone,

            'hangVeMatHangKhongNhapDuocGoogle' => $hangVeMatHangKhongNhapDuocGoogle,
            'hangVeMatHangKhongNhapDuocGoogleNotPhone' => $hangVeMatHangKhongNhapDuocGoogleNotPhone,
            'hangVeMatHangKhongNhapDuoc' => $hangVeMatHangKhongNhapDuoc,
            'hangVeMatHangKhongNhapDuocNotPhone' => $hangVeMatHangKhongNhapDuocNotPhone,
        ];
    }

    private function khachHangTheoNhanChatBiz($weekStartDate, $weekEndDate): array
    {
        $data = array("date_start" => $weekStartDate, "date_end" => $weekEndDate);
        $results =  $this->curl($data, self::CHAT_BIZ_URI, "GET");

        $chatBizKhongCoNhuCau = [];
        $chatBizKhongCoNhuCauNotPhone = [];

        $chatBizKhongNhapDuoc = [];
        $chatBizKhongNhapDuocNotPhone = [];

        $chatBizKhongTuongTacDuoc = [];
        $chatBizKhongTuongTacDuocNotPhone = [];

        $chatBizTuVanKyGuiAndThanhToanHo = [];
        $chatBizTuVanKyGuiAndThanhToanHoNotPhone = [];

        $chatBizKinhDoanh = [];
        $chatBizKinhDoanhNotPhone = [];

        $chatBizTieuDung = [];
        $chatBizTieuDungNotPhone = [];
        if (!empty($results->data)) {
            foreach ($results->data as $value) {
                if (!empty($value->labels)) {
                    foreach ($value->labels as $label) {
                        if ($label->id == self::CHAT_BIZ_KHONG_CO_NHU_CAU) {
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

                        if ($label->id == self::CHAT_BIZ_KHONG_NHAP_DUOC) {
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

                        if ($label->id == self::CHAT_BIZ_KHONG_TUONG_TAC_DUOC) {
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

                        if ($label->id == self::CHAT_BIZ_KH_TU_VAN_KY_GUI || $label->id == self::CHAT_BIZ_KH_THANH_TOAN_HO) {
                            if (!empty($value->phone_number)) {
                                $chatBizTuVanKyGuiAndThanhToanHo[] = $value->phone_number;
                            } else {
                                $chatBizTuVanKyGuiAndThanhToanHoNotPhone[] = [
                                    'customerId' => $value->visitor_id,
                                    'social' => $value->social,
                                    'type' => $value->type,
                                    'phone_number' => '',
                                    'labelName' => $label->text,
                                    'labelId' => $label->id
                                ];
                            }
                        }

                        if ($label->id == self::CHAT_BIZ_KHACH_HANG_KINH_DOANH) {
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

                        if ($label->id == self::CHAT_BIZ_KHACH_HANG_TIEU_DUNG) {
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
            'chatBizTuVanKyGuiAndThanhToanHo' => $chatBizTuVanKyGuiAndThanhToanHo,
            'chatBizTuVanKyGuiAndThanhToanHoNotPhone' => $chatBizTuVanKyGuiAndThanhToanHoNotPhone,
            'chatBizKinhDoanh' => $chatBizKinhDoanh,
            'chatBizKinhDoanhNotPhone' => $chatBizKinhDoanhNotPhone,
            'chatBizTieuDung' => $chatBizTieuDung,
            'chatBizTieuDungNotPhone' => $chatBizTieuDungNotPhone,
        ];
    }

    private function filterDataCustomerWithStatus($startDate, $endDate): array
    {
        $khachHangTheoNhanChatBiz = $this->khachHangTheoNhanChatBiz($startDate, $endDate);
        $khachHangKinhDoanhTieuDungHangVe = $this->khachHangKinhDoanhTieuDungHangVe(
            $startDate. ' 00:00:00',
            $endDate. ' 23:59:59'
        );
        $mappingKhachHangKinhDoanhTieuDung = $this->mappingKhachHangKinhDoanhTieuDung(
            $khachHangTheoNhanChatBiz,
            $khachHangKinhDoanhTieuDungHangVe
        );
        extract($mappingKhachHangKinhDoanhTieuDung, EXTR_PREFIX_SAME, "wddx");
        $khachHangTuVanKyGui = $this->khachHangTuVanKyGui(
            $startDate. ' 00:00:00',
            $endDate. ' 23:59:59'
        );
        $mappingKhachHangTuVanKyGui = $this->mappingKhachHangTuVanKyGui(
            $khachHangTheoNhanChatBiz,
            $khachHangTuVanKyGui
        );
        extract($mappingKhachHangTuVanKyGui, EXTR_PREFIX_SAME, "wddx");
        $khachHangTheoNhan = $this->khachHangTheoNhan(
            $startDate.' 00:00:00',
            $endDate.' 23:59:59'
        );
        $mappingKhachHangKhongCoNhuCau = $this->mappingKhachHangKhongCoNhuCau(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        extract($mappingKhachHangKhongCoNhuCau, EXTR_PREFIX_SAME, "wddx");
        $mappingKhachHangThamKhaoChuaCoNhuCau = $this->mappingKhachHangThamKhaoChuaCoNhuCau($khachHangTheoNhan);
        extract($mappingKhachHangThamKhaoChuaCoNhuCau, EXTR_PREFIX_SAME, "wddx");
        $mappingKhachHangCoNhuCauNhungKhongNhapDuoc = $this->mappingKhachHangCoNhuCauNhungKhongNhapDuoc(
            $khachHangTheoNhanChatBiz,
            $khachHangTheoNhan
        );
        extract($mappingKhachHangCoNhuCauNhungKhongNhapDuoc, EXTR_PREFIX_SAME, "wddx");
        $khachHangNhanTinKhongPhanHoi = $this->khachHangNhanTinKhongPhanHoi($khachHangTheoNhanChatBiz);
        extract($khachHangNhanTinKhongPhanHoi, EXTR_PREFIX_SAME, "wddx");
        $khachHangGoiDienKhongNgheMay = $this->khachHangGoiDienKhongNgheMay($khachHangTheoNhan);
        extract($khachHangGoiDienKhongNgheMay, EXTR_PREFIX_SAME, "wddx");
        return [
            'totalKinhDoanhFacebookZalo' => $totalKinhDoanhFacebookZalo,
            'totalKinhDoanhGoogle' => $totalKinhDoanhGoogle,
            'totalTieuDungFacebookZalo' => $totalTieuDungFacebookZalo,
            'totalTieuDungGoogle' => $totalTieuDungGoogle,
            'totalKyGuiFacebookZalo' => $totalKyGuiFacebookZalo,
            'totalKyGuiGoogle' => $totalKyGuiGoogle,
            'totalKhachHangKhongCoNhuCauFacebookZalo' => $totalKhachHangKhongCoNhuCauFacebookZalo,
            'totalKhachHangKhongCoNhuCauGoogle' => $totalKhachHangKhongCoNhuCauGoogle,
            'totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo' => $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo,
            'totalKhachHangThamKhaoChuaCoNhuCauGoogle' => $totalKhachHangThamKhaoChuaCoNhuCauGoogle,
            'totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo' => $totalKhachHangCoNhuCauNhungKhongNhapDuocFacebookZalo,
            'totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle' => $totalKhachHangCoNhuCauNhungKhongNhapDuocGoogle,
            'totalKhachHangNhanTinKhongPhanHoiFacebookZalo' => $totalKhachHangNhanTinKhongPhanHoiFacebookZalo,
            'hangVeKhachHangGoiKhongNgheMayGoogle' => $hangVeKhachHangGoiKhongNgheMayGoogle,
            'hangVeKhachHangGoiKhongNgheMay' => $hangVeKhachHangGoiKhongNgheMay,
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
    /* lấy khách hàng ký gửi bên hang ve */
    private function khachHangTuVanKyGui($weekStartDate, $weekEndDate){
        return Customer::select(
            DB::raw('
                DISTINCT customers.id as customerId,
                customers.name as customerName,
                customers.via as social,
                customers.phone_number as customerPhone'
            )
        )
        ->whereIn('customers.via', [self::GOOGLE_ADS, self::FACEBOOK_ADS, self::GOOGLE, self::ZALO])
        ->where('customers.service', self::CONSIGNMENT)
        ->whereNotnull('customers.staff_group_id')
        ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
        ->get()->toArray();
    }
    /*lay khach hang kinh doanh va tieu dung all bên hàng về*/
    private function khachHangKinhDoanhTieuDungHangVe($weekStartDate, $weekEndDate){
        return Customer::select(
                'customers.id as customerId',
                'customers.phone_number as customerPhone',
                'customers.via as social',
                'customers.business_type as businessType',
                'customers.service as customerService'
            )
            ->whereIn('customers.via', [self::GOOGLE_ADS, self::FACEBOOK_ADS, self::GOOGLE, self::ZALO])
            ->whereIn('customers.business_type', [self::BUSINESS, self::PERSONAL])
            ->whereBetween('customers.created_at',[$weekStartDate, $weekEndDate])
            ->get()->toArray();
    }

    /*Khách hàng lên đơn : Kinh doanh và Tiêu dùng*/
    private function khachHangDatHang($weekStartDate, $weekEndDate): array
    {
        $results = Customer::select(
            DB::raw('
                DISTINCT customers.id as customerId,
                customers.name as customerName,
                customers.via as social,
                orders.status as orderStatus,
                customers.business_type as businessType,
                customers.phone_number as customerPhone'
            )
        )
        ->join('orders','orders.customer_id','=','customers.id')
        ->whereIn('orders.status', [OrderStatus::DAT_HANG, OrderStatus::DA_DAT_COC])
        ->whereIn('customers.via', [self::GOOGLE_ADS, self::FACEBOOK_ADS, self::GOOGLE, self::ZALO])
        ->whereBetween('customers.last_order_at',[$weekStartDate, $weekEndDate])
        ->get()->toArray();
        $khachHangKinhDoanhGoogle = [];
        $khachHangKinhDoanhFacebookZalo = [];
        $khachHangTieuDungFacebookZalo = [];
        $khachHangTieuDungGoogle = [];
        foreach ($results as $value) {
            if ($value['businessType'] == self::PERSONAL) {
                if (in_array($value['social'], [self::GOOGLE, self::GOOGLE_ADS])) {
                    $khachHangTieuDungGoogle[] = $value;
                }
                if (in_array($value['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                    $khachHangTieuDungFacebookZalo[] = $value;
                }
            }
            if ($value['businessType'] == self::BUSINESS) {
                if (in_array($value['social'], [self::GOOGLE, self::GOOGLE_ADS])) {
                    $khachHangKinhDoanhGoogle[] = $value;
                }
                if (in_array($value['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                    $khachHangKinhDoanhFacebookZalo[] = $value;
                }
            }
        }
       return [
           'khachHangDatHangKinhDoanhGoogle' => sizeof($khachHangKinhDoanhGoogle),
           'khachHangDatHangKinhDoanhFacebookZalo' => sizeof($khachHangKinhDoanhFacebookZalo),
           'khachHangDatHangTieuDungFacebookZalo' => sizeof($khachHangTieuDungFacebookZalo),
           'khachHangDatHangTieuDungGoogle' => sizeof($khachHangTieuDungGoogle)
       ];
    }

    /*Khách hàng dang follow lên đơn*/
    private function khachHangFollow($weekStartDate, $weekEndDate): array
    {
        $results = Customer::select(
            DB::raw('
                DISTINCT customers.id as customerId,
                customers.name as customerName,
                customers.via as social,
                customers.phone_number as customerPhone'
            )
        )
        ->join('orders','orders.customer_id','=','customers.id')
        ->join('labels','customers.label_id','=','labels.id')
        ->where('labels.code',self::DANG_FOLLOW)
        ->whereIn('customers.via', [self::GOOGLE_ADS, self::FACEBOOK_ADS, self::GOOGLE, self::ZALO])
        ->whereBetween('customers.last_order_at',[$weekStartDate, $weekEndDate])
        ->get()->toArray();
        $khachHangFollowGoogle = [];
        $khachHangFollowFacebookZalo = [];
        foreach ($results as $value) {
            if (in_array($value['social'], [self::GOOGLE, self::GOOGLE_ADS])) {
                $khachHangFollowGoogle[] = $value;
            }
            if (in_array($value['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                $khachHangFollowFacebookZalo[] = $value;
            }
        }
        return [
            'khachHangFollowGoogle' => sizeof($khachHangFollowGoogle),
            'khachHangFollowFacebookZalo' => sizeof($khachHangFollowFacebookZalo)
        ];
    }

    /*Lấy tổng khách hàng Kinh Doanh Tieu Dung bên Hang ve + chat Biz : Zalo, FB, GG*/
    private function mappingKhachHangKinhDoanhTieuDung(array $khachHangTheoNhanChatBiz, array $khachHangKinhDoanhTieuDungHangVe): array
    {
        extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        $hangVeKinhDoanh = [];
        $hangVeTieuDung = [];
        $hangVeKinhDoanhNotPhone = [];
        $hangVeTieuDungNotPhone = [];
        $hangVeKinhDoanhGoogle = [];
        $hangVeTieuDungGoogle = [];
        foreach ($khachHangKinhDoanhTieuDungHangVe as $value) {
            if (in_array($value['social'], [self::FACEBOOK_ADS, self::ZALO])) {
                if (!empty($value['customerPhone'])) {
                    if ($value['businessType'] == self::BUSINESS) {
                        $hangVeKinhDoanh[] = $value['customerPhone'];
                    }
                    if ($value['businessType'] == self::PERSONAL) {
                        $hangVeTieuDung[] = $value['customerPhone'];
                    }
                } else {
                    if ($value['businessType'] == self::BUSINESS) {
                        $hangVeKinhDoanhNotPhone[] = $value;
                    }
                    if ($value['businessType'] == self::PERSONAL) {
                        $hangVeTieuDungNotPhone[] = $value;
                    }
                }
            }
            if (in_array($value['social'], [self::GOOGLE, self::GOOGLE_ADS])) {
                if ($value['businessType'] == self::BUSINESS) {
                    $hangVeKinhDoanhGoogle[] = $value;
                }
                if ($value['businessType'] == self::PERSONAL) {
                    $hangVeTieuDungGoogle[] = $value;
                }
            }
        }
        $totalKinhDoanhFacebookZalo = $this->uniquePhoneNumber($chatBizKinhDoanh, $hangVeKinhDoanh)
            + sizeof($chatBizKinhDoanhNotPhone)
            + sizeof($hangVeKinhDoanhNotPhone);
        $totalKinhDoanhGoogle = sizeof($hangVeKinhDoanhGoogle);

        $totalTieuDungFacebookZalo = $this->uniquePhoneNumber($chatBizTieuDung, $hangVeTieuDung)
            + sizeof($chatBizTieuDungNotPhone)
            + sizeof($hangVeTieuDungNotPhone);
        $totalTieuDungGoogle = sizeof($hangVeTieuDungGoogle);
        return [
            'totalKinhDoanhFacebookZalo' => $totalKinhDoanhFacebookZalo,
            'totalKinhDoanhGoogle' => $totalKinhDoanhGoogle,
            'totalTieuDungFacebookZalo' => $totalTieuDungFacebookZalo,
            'totalTieuDungGoogle' => $totalTieuDungGoogle,
        ];
    }

    /*Lấy tổng khách hàng tư vấn ký gửi bên Hang ve + chat Biz : Zalo, FB, GG*/
    private function mappingKhachHangTuVanKyGui(array $khachHangTheoNhanChatBiz, array $khachHangTuVanKyGui): array
    {
        extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        $hangVeKhachHangKyGuiGoogle = [];
        $hangVeKhachHangKyGui = [];
        $hangVeKhachHangKyGuiNotPhone = [];
        foreach ($khachHangTuVanKyGui as $value) {
            if ($value['social'] == self::GOOGLE_ADS || $value['social'] == self::GOOGLE) {
                $hangVeKhachHangKyGuiGoogle[] = $value;
            }
            if ($value['social'] == self::FACEBOOK_ADS || $value['social'] == self::ZALO) {
                if (!empty($value['customerPhone'])) {
                    $hangVeKhachHangKyGui[] = $value['customerPhone'];
                } else {
                    $hangVeKhachHangKyGuiNotPhone[] = $value;
                }
            }
        }
        $totalKyGuiFacebookZalo = $this->uniquePhoneNumber($hangVeKhachHangKyGui, $chatBizTuVanKyGuiAndThanhToanHo)
            + sizeof($hangVeKhachHangKyGuiNotPhone)
            + sizeof($chatBizTuVanKyGuiAndThanhToanHoNotPhone);
        $totalKyGuiGoogle = sizeof($hangVeKhachHangKyGuiGoogle);
        return [
            'totalKyGuiFacebookZalo' => $totalKyGuiFacebookZalo,
            'totalKyGuiGoogle' => $totalKyGuiGoogle,
        ];
    }

    /*Lấy tổng khách hàng KH không có nhu cầu bên Hang ve + chat Biz : Zalo, FB, GG*/
    private function mappingKhachHangKhongCoNhuCau(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");
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

    /*Lấy tổng khách hàng KH Tham khảo chưa có nhu cầu ngay Hang ve  : Zalo, FB, GG*/
    private function mappingKhachHangThamKhaoChuaCoNhuCau(array $khachHangTheoNhan): array
    {
        extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");
        $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo = sizeof($hangVeThamKhaoChuaCoNhuCauNgay)
            + sizeof($hangVeThamKhaoChuaCoNhuCauNgayNotPhone);
        $totalKhachHangThamKhaoChuaCoNhuCauGoogle = sizeof($hangVeThamKhaoChuaCoNhuCauNgayGoogle)
            + sizeof($hangVeThamKhaoChuaCoNhuCauNgayGoogleNotPhone);
        return [
            'totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo' => $totalKhachHangThamKhaoChuaCoNhuCauFacebookZalo,
            'totalKhachHangThamKhaoChuaCoNhuCauGoogle' => $totalKhachHangThamKhaoChuaCoNhuCauGoogle,
        ];
    }
    /*Lấy tổng khách hàng KH có nhu cầu nhưng mặt hàng không nhập đươc Hang ve + ChatBiz  : Zalo, FB, GG*/
    private function mappingKhachHangCoNhuCauNhungKhongNhapDuoc(array $khachHangTheoNhanChatBiz, array $khachHangTheoNhan): array
    {
        extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");
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

    private function khachHangNhanTinKhongPhanHoi(array $khachHangTheoNhanChatBiz): array
    {
        extract($khachHangTheoNhanChatBiz, EXTR_PREFIX_SAME, "wddx");
        return [
            'totalKhachHangNhanTinKhongPhanHoiFacebookZalo' => sizeof($chatBizKhongTuongTacDuoc) + sizeof($chatBizKhongTuongTacDuocNotPhone),
        ];
    }
    private function khachHangGoiDienKhongNgheMay(array $khachHangTheoNhan): array
    {
        extract($khachHangTheoNhan, EXTR_PREFIX_SAME, "wddx");
        return [
            'hangVeKhachHangGoiKhongNgheMayGoogle' => sizeof($hangVeKhachHangGoiKhongNgheMayGoogle)
                + sizeof($hangVeKhachHangGoiKhongNgheMayGoogleNotPhone),
            'hangVeKhachHangGoiKhongNgheMay' => sizeof($hangVeKhachHangGoiKhongNgheMay)
                + sizeof($hangVeKhachHangGoiKhongNgheMayNotPhone),
        ];
    }

    private function curl($data, $url, $method){
        try {
            /*$params = [
                'query' => [
                    'date_start' => $data['date_start'],
                    'date_end' => $data['date_start']
                ]
            ];
            $guzzle_client = new Client();
            $response = $guzzle_client->request($method, $url, $params);
            if ($response->getStatusCode() == 200) {
                Log::info('GET data from chatBiz ReportsControlMarketing');
                return json_decode($response->getBody());
            } else {
                Log::info('GET data from chatBiz ReportsControlMarketing Fail');
                return false;
            }*/
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
            Log::info('GET data from chatBiz ReportsControlMarketing '. $data_string);
            return json_decode($result,false);
        } catch (\Throwable $e) {
            Log::info('GET data from chatBiz ReportsControlMarketing Error : '.$e->getMessage());
            return false;
        }
    }

    public function insert($data){
        try {
            ReportControlMarketing::insert($data);
            return true;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    public function delete($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportControlMarketing::where('week', $list->week)
            ->where('year', $list->year)
            ->where('month', $list->month)
            ->where('type','week')
            ->delete();
    }

    public function deleteMonth($request){
        $list = CalculatorWeekReport::getWeekMonthYear($request);
        return ReportControlMarketing::where('year', $list->year)
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
        $query = ReportControlMarketing::query();
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
        $resultEnd = $queryEnd->first()->toArray();
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
        $resultStart = $queryStart->first()->toArray();
        $reportStart = (new ReportControlMarketingStart($resultStart))
            ->totalCustomerAll()
            ->totalCustomerFacebookZalo()
            ->totalCustomerGoogle()
            ->totalCustomerInteract()
            ->totalCustomerInteractNotNeed()
            ->totalCustomerInteractNotImmediateNeed()
            ->totalCustomerInteractNotImported()
            ->totalCustomerInteractConsignment()
            ->totalCustomerInteractOrderBusiness()
            ->totalCustomerInteractOrderPersonal()
            ->totalCustomerInteractFollow()
            ->totalCustomerNotInteract()
            ->totalCustomerNotInteractTextedNotRespond()
            ->totalCustomerNotInteractCallingNotAnswer()
            ->totalCustomerPersonalBusiness()
            ->totalCustomerPersonal()
            ->totalCustomerBusiness()
            ->exec();
        $reportEnd = (new ReportControlMarketingStart($resultEnd))
            ->totalCustomerAll()
            ->totalCustomerFacebookZalo()
            ->totalCustomerGoogle()
            ->totalCustomerInteract()
            ->totalCustomerInteractNotNeed()
            ->totalCustomerInteractNotImmediateNeed()
            ->totalCustomerInteractNotImported()
            ->totalCustomerInteractConsignment()
            ->totalCustomerInteractOrderBusiness()
            ->totalCustomerInteractOrderPersonal()
            ->totalCustomerInteractFollow()
            ->totalCustomerNotInteract()
            ->totalCustomerNotInteractTextedNotRespond()
            ->totalCustomerNotInteractCallingNotAnswer()
            ->totalCustomerPersonalBusiness()
            ->totalCustomerPersonal()
            ->totalCustomerBusiness()
            ->exec();
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

        if (empty($resultEnd) || empty($resultStart)) {
            return [];
        }

        $isDisplayWeekEnd =  $weekEnd ? 'Tuần '.$weekEnd : '';
        $isDisplayWeekStart =  $weekStart ? 'Tham chiếu tuần '.$weekStart : '';
        $data['format'] =
        [
            [
                'data_one' => 'Đối tượng KH',
                'data_two' => $isDisplayWeekStart.' tháng '.$monthStart.'/'.$yearStart.' ( '.$startDateStartOfWeek .' đến '.$endDateStartOfWeek.' )',
                'data_three' => $isDisplayWeekEnd.' tháng '.$monthEnd.'/'.$yearEnd.' ( '.$startDateEndOfWeek .' đến '.$endDateEndOfWeek.' )',
                'data_four' => '% Tăng trưởng',
            ],
            [
                'data_one' => 'Tổng KH',
                'data_two' => number_format($reportStart->totalCustomerAll, 0, ',', '.'),
                'data_three' => number_format($reportEnd->totalCustomerAll, 0, ',', '.'),
                'data_four' => $reportStart->totalCustomerAll > 0 ? round(
                    ($reportEnd->totalCustomerAll - $reportStart->totalCustomerAll)*100/$reportStart->totalCustomerAll
                    ).'%': 0 .'%',
            ],
            [
                'data_one' => 'KH fanpage + Zalo OA',
                'data_two' => number_format($reportStart->totalCustomerFacebookZaloAll, 0, ',', '.'),
                'data_three' => number_format($reportEnd->totalCustomerFacebookZaloAll, 0, ',', '.'),
                'data_four' => $reportStart->totalCustomerFacebookZaloAll > 0 ? round(
                    ($reportEnd->totalCustomerFacebookZaloAll - $reportStart->totalCustomerFacebookZaloAll)*100/$reportStart->totalCustomerFacebookZaloAll
                    ).'%': 0 .'%',
            ],
            [
                'data_one' => 'KH google',
                'data_two' => number_format($reportStart->totalCustomerGoogleAll, 0, ',','.'),
                'data_three' => number_format($reportEnd->totalCustomerGoogleAll, 0,',','.'),
                'data_four' => $reportStart->totalCustomerGoogleAll > 0 ? round(
                    ($reportEnd->totalCustomerGoogleAll - $reportStart->totalCustomerGoogleAll)*100/$reportStart->totalCustomerGoogleAll
                    ).'%': 0 .'%',
            ],
            [
                'data_one' => 'Tổng KH tương tác được',
                'data_two' => number_format($reportStart->totalCustomerInteractAll,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractAll.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractFacebookZalo,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractFacebookZalo.'%',
                'data_six' => number_format($reportStart->totalCustomerInteractGoogle,0,',','.'),
                'data_seven' => $reportStart->tyLeCustomerInteractGoogle.'%',

                'data_eight' => number_format($reportEnd->totalCustomerInteractAll,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractAll.'%',
                'data_ten' => number_format($reportEnd->totalCustomerInteractFacebookZalo,0,',','.'),
                'data_eleven' => $reportEnd->tyLeCustomerInteractFacebookZalo.'%',
                'data_twelve' => number_format($reportEnd->totalCustomerInteractGoogle,0,',','.'),
                'data_thirteen' => $reportEnd->tyLeCustomerInteractGoogle.'%',

                'data_fourteen' => $reportStart->totalCustomerInteractAll > 0 ? round(
                    ($reportEnd->totalCustomerInteractAll - $reportStart->totalCustomerInteractAll)*100/$reportStart->totalCustomerInteractAll
                ).'%': 0 .'%',
            ],
            [
                'data_one' => 'KH không có nhu cầu',

                'data_two' => number_format($reportStart->totalCustomerInteractNotNeedFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractNotNeedFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractNotNeedGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractNotNeedGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractNotNeedFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractNotNeedFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractNotNeedGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractNotNeedGoogle.'%',
            ],
            [
                'data_one' => 'KH Tham khảo chưa có nhu cầu ngay',

                'data_two' => number_format($reportStart->totalCustomerInteractImmediateNotNeedFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractImmediateNotNeedFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractImmediateNotNeedGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractImmediateNotNeedGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractImmediateNotNeedFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractImmediateNotNeedFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractImmediateNotNeedGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractImmediateNotNeedGoogle.'%',
            ],
            [
                'data_one' => 'KH có nhu cầu nhưng mặt hàng không nhập đươc',

                'data_two' => number_format($reportStart->totalCustomerInteractNotImportedFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractNotImportedFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractNotImportedGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractNotImportedGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractNotImportedFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractNotImportedFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractNotImportedGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractNotImportedGoogle.'%',
            ],
            [
                'data_one' => 'KH tư vấn ký gửi',

                'data_two' => number_format($reportStart->totalCustomerInteractConsignmentFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractConsignmentFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractConsignmentGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractConsignmentGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractConsignmentFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractConsignmentFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractConsignmentGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractConsignmentGoogle.'%',
            ],
            [
                'data_one' => 'Kinh doanh',

                'data_two' => number_format($reportStart->totalCustomerInteractOrderBusinessFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractOrderBusinessFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractOrderBusinessGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractOrderBusinessGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractOrderBusinessFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractOrderBusinessFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractOrderBusinessGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractOrderBusinessGoogle.'%',
            ],
            [
                'data_one' => 'Tiêu dùng',

                'data_two' => number_format($reportStart->totalCustomerInteractOrderPersonalFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractOrderPersonalFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractOrderPersonalGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractOrderPersonalGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractOrderPersonalFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractOrderPersonalFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractOrderPersonalGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractOrderPersonalGoogle.'%',
            ],
            [
                'data_one' => 'KH đang follow lên đơn',

                'data_two' => number_format($reportStart->totalCustomerInteractFollowFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerInteractFollowFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerInteractFollowGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerInteractFollowGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerInteractFollowFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerInteractFollowFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerInteractFollowGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerInteractFollowGoogle.'%',
            ],
            [
                'data_one' => 'Tổng KH không tương tác được',

                'data_two' => number_format($reportStart->totalCustomerNotInteractAll,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerNotInteractAll.'%',
                'data_four' => number_format($reportStart->totalCustomerNotInteractFacebookZalo,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerNotInteractFacebookZalo.'%',
                'data_six' => number_format($reportStart->totalCustomerNotInteractGoogle,0,',','.'),
                'data_seven' => $reportStart->tyLeCustomerNotInteractGoogle.'%',

                'data_eight' => number_format($reportEnd->totalCustomerNotInteractAll,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerNotInteractAll.'%',
                'data_ten' => number_format($reportEnd->totalCustomerNotInteractFacebookZalo,0,',','.'),
                'data_eleven' => $reportEnd->tyLeCustomerNotInteractFacebookZalo.'%',
                'data_twelve' => number_format($reportEnd->totalCustomerNotInteractGoogle,0,',','.'),
                'data_thirteen' => $reportEnd->tyLeCustomerNotInteractGoogle.'%',

                'data_fourteen' => $reportStart->totalCustomerNotInteractAll > 0 ? round(
                        ($reportEnd->totalCustomerNotInteractAll - $reportStart->totalCustomerNotInteractAll)*100/$reportStart->totalCustomerNotInteractAll
                    ).'%': 0 .'%',
            ],
            [
                'data_one' => 'KH nhắn tin không phản hồi',

                'data_two' => number_format($reportStart->totalCustomerNotInteractTextedNotRespondFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerNotInteractTextedNotRespondFacebookZalo.'%',
                'data_four' => 0,
                'data_five' => '0%',

                'data_six' => number_format($reportEnd->totalCustomerNotInteractTextedNotRespondFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerNotInteractTextedNotRespondFacebookZalo.'%',
                'data_eight' => 0,
                'data_nine' => '0%',
            ],
            [
                'data_one' => 'KH gọi không nghe máy',

                'data_two' => number_format($reportStart->totalCustomerNotInteractCallingNotAnswerFacebookZalo,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerNotInteractCallingNotAnswerFacebookZalo.'%',
                'data_four' => number_format($reportStart->totalCustomerNotInteractCallingNotAnswerGoogle,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerNotInteractCallingNotAnswerGoogle.'%',

                'data_six' => number_format($reportEnd->totalCustomerNotInteractCallingNotAnswerFacebookZalo,0,',','.'),
                'data_seven' => $reportEnd->tyLeCustomerNotInteractCallingNotAnswerFacebookZalo.'%',
                'data_eight' => number_format($reportEnd->totalCustomerNotInteractCallingNotAnswerGoogle,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerNotInteractCallingNotAnswerGoogle.'%',
            ],
            [
                'data_one' => 'KH Kinh doanh',

                'data_two' => number_format($reportStart->totalCustomerBusinessAll,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerBusinessAll.'%',
                'data_four' => number_format($reportStart->totalCustomerBusinessFacebookZalo,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerBusinessFacebookZalo.'%',
                'data_six' => number_format($reportStart->totalCustomerBusinessGoogle,0,',','.'),
                'data_seven' => $reportStart->tyLeCustomerBusinessGoogle.'%',

                'data_eight' => number_format($reportEnd->totalCustomerBusinessAll,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerBusinessAll.'%',
                'data_ten' => number_format($reportEnd->totalCustomerBusinessFacebookZalo,0,',','.'),
                'data_eleven' => $reportEnd->tyLeCustomerBusinessFacebookZalo.'%',
                'data_twelve' => number_format($reportEnd->totalCustomerBusinessGoogle,0,',','.'),
                'data_thirteen' => $reportEnd->tyLeCustomerBusinessGoogle.'%',

                'data_fourteen' => $reportStart->totalCustomerBusinessAll > 0 ? round(
                        ($reportEnd->totalCustomerBusinessAll - $reportStart->totalCustomerBusinessAll)*100/$reportStart->totalCustomerBusinessAll
                    ).'%': 0 .'%',
            ],

            [
                'data_one' => 'KH tiêu dùng',

                'data_two' => number_format($reportStart->totalCustomerPersonalAll,0,',','.'),
                'data_three' => $reportStart->tyLeCustomerPersonalAll.'%',
                'data_four' => number_format($reportStart->totalCustomerPersonalFacebookZalo,0,',','.'),
                'data_five' => $reportStart->tyLeCustomerPersonalFacebookZalo.'%',
                'data_six' => number_format($reportStart->totalCustomerPersonalGoogle,0,',','.'),
                'data_seven' => $reportStart->tyLeCustomerPersonalGoogle.'%',

                'data_eight' => number_format($reportEnd->totalCustomerPersonalAll,0,',','.'),
                'data_nine' => $reportEnd->tyLeCustomerPersonalAll.'%',
                'data_ten' => number_format($reportEnd->totalCustomerPersonalFacebookZalo,0,',','.'),
                'data_eleven' => $reportEnd->tyLeCustomerPersonalFacebookZalo.'%',
                'data_twelve' => number_format($reportEnd->totalCustomerPersonalGoogle,0,',','.'),
                'data_thirteen' => $reportEnd->tyLeCustomerPersonalGoogle.'%',

                'data_fourteen' => $reportStart->totalCustomerPersonalAll > 0 ? round(
                        ($reportEnd->totalCustomerPersonalAll - $reportStart->totalCustomerPersonalAll)*100/$reportStart->totalCustomerPersonalAll
                    ).'%': 0 .'%',
            ],
        ];
        return $data;
    }
    private function totalCustomerAll($resultStart, $resultEnd): array
     {
         $totalCustomerStartAll = 0;
         $totalCustomerStartAll += $resultStart['total_customer_business_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_business_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_business_google'];
         $totalCustomerStartAll += $resultStart['total_customer_personal_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_personal_google'];
         $totalCustomerStartAll += $resultStart['total_customer_texted_not_respond'];
         $totalCustomerStartAll += $resultStart['total_customer_calling_not_answer_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_calling_not_answer_google'];
         $totalCustomerStartAll += $resultStart['total_customer_order_follow_google'];
         $totalCustomerStartAll += $resultStart['total_customer_order_follow_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_order_personal_google'];
         $totalCustomerStartAll += $resultStart['total_customer_order_business_google'];
         $totalCustomerStartAll += $resultStart['total_customer_order_personal_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_order_business_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_consignment_advise_google'];
         $totalCustomerStartAll += $resultStart['total_customer_consignment_advise_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_need_but_item_not_imported_google'];
         $totalCustomerStartAll += $resultStart['total_customer_need_but_item_not_imported_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_not_immediate_need_google'];
         $totalCustomerStartAll += $resultStart['total_customer_not_immediate_need_facebook'];
         $totalCustomerStartAll += $resultStart['total_customer_not_need_google'];
         $totalCustomerStartAll += $resultStart['total_customer_not_need_facebook'];

         $totalCustomerEndAll = 0;
         $totalCustomerEndAll += $resultEnd['total_customer_business_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_business_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_business_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_personal_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_personal_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_texted_not_respond'];
         $totalCustomerEndAll += $resultEnd['total_customer_calling_not_answer_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_calling_not_answer_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_follow_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_follow_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_personal_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_business_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_personal_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_order_business_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_consignment_advise_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_consignment_advise_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_need_but_item_not_imported_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_need_but_item_not_imported_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_not_immediate_need_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_not_immediate_need_facebook'];
         $totalCustomerEndAll += $resultEnd['total_customer_not_need_google'];
         $totalCustomerEndAll += $resultEnd['total_customer_not_need_facebook'];

         $tyLeAll =  $totalCustomerStartAll > 0 ? round(
             ($totalCustomerEndAll - $totalCustomerStartAll)*100/ $totalCustomerStartAll
         ) : 0;

         return [
             'totalCustomerEndAll' => number_format($totalCustomerEndAll,0,',','.'),
             'totalCustomerStartAll' => number_format($totalCustomerStartAll,0,',','.'),
             'tyLeAll' => $tyLeAll
         ];
     }

    private function totalCustomerFacebookZalo($resultStart, $resultEnd): array
     {
        $totalCustomerFacebookZaloStartAll = 0;
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_business_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_personal_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_texted_not_respond'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_calling_not_answer_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_order_follow_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_order_personal_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_order_business_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerFacebookZaloStartAll += $resultStart['total_customer_not_need_facebook'];

        $totalCustomerFacebookZaloEndAll = 0;
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_business_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_personal_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_texted_not_respond'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_calling_not_answer_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_order_follow_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_order_personal_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_order_business_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_consignment_advise_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_not_immediate_need_facebook'];
        $totalCustomerFacebookZaloEndAll += $resultEnd['total_customer_not_need_facebook'];

        $tyLeFacebookZaloAll = $totalCustomerFacebookZaloStartAll > 0 ? round(
             ($totalCustomerFacebookZaloEndAll - $totalCustomerFacebookZaloStartAll)*100/ $totalCustomerFacebookZaloStartAll
        ) : 0;

        return [
            'totalCustomerFacebookZaloStartAll' => number_format($totalCustomerFacebookZaloStartAll,0,',','.'),
            'totalCustomerFacebookZaloEndAll' => number_format($totalCustomerFacebookZaloEndAll,0,',','.'),
            'tyLeFacebookZaloAll' => $tyLeFacebookZaloAll,
        ];
     }

    private function totalCustomerGoogle($resultStart, $resultEnd): array
    {
        $totalCustomerGoogleStartAll = 0;
        $totalCustomerGoogleStartAll += $resultStart['total_customer_business_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_personal_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_calling_not_answer_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_order_follow_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_order_personal_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_order_business_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_consignment_advise_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerGoogleStartAll += $resultStart['total_customer_not_need_google'];

        $totalCustomerGoogleEndAll = 0;
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_business_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_personal_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_calling_not_answer_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_order_follow_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_order_personal_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_order_business_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_consignment_advise_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_need_but_item_not_imported_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_not_immediate_need_google'];
        $totalCustomerGoogleEndAll += $resultEnd['total_customer_not_need_google'];

        $tyLeGoogleAll = $totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerGoogleEndAll - $totalCustomerGoogleStartAll)*100/ $totalCustomerGoogleStartAll
        ) : 0;

        return [
            'totalCustomerGoogleStartAll' => number_format($totalCustomerGoogleStartAll,0,',','.'),
            'totalCustomerGoogleEndAll' => number_format($totalCustomerGoogleEndAll,0,',','.'),
            'tyLeGoogleAll' => $tyLeGoogleAll,
        ];
    }
    /*Tổng KH tương tác được*/
    private function totalCustomerInteract($resultStart, $resultEnd): array
    {
        $totalCustomerAll = $this->totalCustomerAll($resultStart, $resultEnd);
        $totalCustomerEndAll = $totalCustomerAll['totalCustomerEndAll'];
        $totalCustomerStartAll = $totalCustomerAll['totalCustomerStartAll'];
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */
        $totalCustomerInteractStartAll = 0;

        $totalCustomerInteractStartAll += $resultStart['total_customer_order_follow_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_order_follow_facebook'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_order_personal_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_order_business_google'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_order_personal_facebook'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_order_business_facebook'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_consignment_advise_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_consignment_advise_facebook'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_need_but_item_not_imported_facebook'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_not_immediate_need_facebook'];

        $totalCustomerInteractStartAll += $resultStart['total_customer_not_need_google'];
        $totalCustomerInteractStartAll += $resultStart['total_customer_not_need_facebook'];

        $tyLeCustomerInteractStartAll = (int)$totalCustomerStartAll > 0 ? round(
            ($totalCustomerInteractStartAll * 100) / $totalCustomerStartAll
        ) : 0;

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_follow_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_personal_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_business_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_not_need_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;
        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_follow_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_personal_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_business_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_consignment_advise_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_not_need_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */
        $totalCustomerInteractEndAll = 0;

        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_follow_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_follow_facebook'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_personal_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_business_google'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_personal_facebook'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_order_business_facebook'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_consignment_advise_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_consignment_advise_facebook'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_need_but_item_not_imported_facebook'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_not_immediate_need_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_not_immediate_need_facebook'];

        $totalCustomerInteractEndAll += $resultEnd['total_customer_not_need_google'];
        $totalCustomerInteractEndAll += $resultEnd['total_customer_not_need_facebook'];

        $tyLeCustomerInteractEndAll = $totalCustomerEndAll > 0 ? round(
            ($totalCustomerInteractEndAll * 100) / $totalCustomerEndAll
        ) : 0;

        $totalCustomerInteractEndFacebookZalo = 0;
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_follow_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_personal_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_business_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_consignment_advise_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_not_immediate_need_facebook'];
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_not_need_facebook'];

        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_follow_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_personal_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_business_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_consignment_advise_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_not_immediate_need_google'];
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_not_need_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        $tyLeCustomerInteractAll = $totalCustomerInteractStartAll > 0 ? round(
            ($totalCustomerInteractEndAll - $totalCustomerInteractStartAll) * 100 /$totalCustomerInteractStartAll
        ) : 0;
        return [
            'tyLeCustomerInteractAll' => $tyLeCustomerInteractAll,

            'tyLeCustomerInteractEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,
            'totalCustomerInteractEndAll' => $totalCustomerInteractEndAll,
            'tyLeCustomerInteractEndAll' => $tyLeCustomerInteractEndAll,

            'totalCustomerInteractStartAll' => $totalCustomerInteractStartAll,
            'tyLeCustomerInteractStartAll' => $tyLeCustomerInteractStartAll,
            'tyLeCustomerInteractStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }
    /*KH không có nhu cầu*/
    private function totalCustomerInteractNotNeed($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /*Tuần tham chiếu*/
        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_not_need_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;
        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_not_need_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;

        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_not_need_facebook'];

        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;

        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_not_need_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [
            'tyLeCustomerInteractNotNeedEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractNotNeedEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractNotNeedEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractNotNeedEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractNotNeedStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractNotNeedStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractNotNeedStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractNotNeedStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }
    /*KH Tham khảo chưa có nhu cầu ngay*/
    private function totalCustomerInteractNotImmediateNeed($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */


        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_not_immediate_need_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_not_immediate_need_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_not_immediate_need_facebook'];

        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_not_immediate_need_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [
            'tyLeCustomerInteractImmediateNotNeedEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractImmediateNotNeedEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractImmediateNotNeedEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractImmediateNotNeedEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractImmediateNotNeedStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractImmediateNotNeedStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractImmediateNotNeedStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractImmediateNotNeedStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }
    /*Khach hang co nhu cau nhung mat hang k nhap duoc*/
    private function totalCustomerInteractNotImported($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_need_but_item_not_imported_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_need_but_item_not_imported_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_need_but_item_not_imported_facebook'];

        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_need_but_item_not_imported_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [

            'tyLeCustomerInteractNotImportedEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractNotImportedEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractNotImportedEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractNotImportedEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractNotImportedStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractNotImportedStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractNotImportedStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractNotImportedStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }
    /*KH tư vấn ký gửi*/
    private function totalCustomerInteractConsignment($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_consignment_advise_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_consignment_advise_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;

        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_consignment_advise_facebook'];

        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;

        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_consignment_advise_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [
            'tyLeCustomerInteractConsignmentEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractConsignmentEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractConsignmentEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractConsignmentEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractConsignmentStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractConsignmentStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractConsignmentStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractConsignmentStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }

    /*Tổng KH lên đơn kinh doanh*/
    private function totalCustomerInteractOrderBusiness($resultStart, $resultEnd): array
    {

        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */


        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_business_facebook'];;
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_business_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;

        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_business_facebook'];
        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_business_google'];
        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [

            'tyLeCustomerInteractOrderBusinessEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractOrderBusinessEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractOrderBusinessEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractOrderBusinessEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractOrderBusinessStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractOrderBusinessStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractOrderBusinessStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractOrderBusinessStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }

    /*Tổng KH lên đơn tiêu dùng*/
    private function totalCustomerInteractOrderPersonal($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_personal_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_personal_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_personal_facebook'];
        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_personal_google'];

        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [
            'tyLeCustomerInteractOrderPersonalEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractOrderPersonalEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractOrderPersonalEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractOrderPersonalEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractOrderPersonalStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractOrderPersonalStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractOrderPersonalStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractOrderPersonalStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }

    /*Tổng KH follow len don*/
    private function totalCustomerInteractFollow($resultStart, $resultEnd): array
    {
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $resultStart['total_customer_order_follow_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $resultStart['total_customer_order_follow_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */

        $totalCustomerInteractEndFacebookZalo = 0;
        $totalCustomerInteractEndFacebookZalo += $resultEnd['total_customer_order_follow_facebook'];
        $tyLeCustomerInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerInteractEndGoogle = 0;
        $totalCustomerInteractEndGoogle += $resultEnd['total_customer_order_follow_google'];
        $tyLeCustomerInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        return [
            'tyLeCustomerInteractFollowEndGoogle' => $tyLeCustomerInteractEndGoogle,
            'totalCustomerInteractFollowEndGoogle' => $totalCustomerInteractEndGoogle,
            'totalCustomerInteractFollowEndFacebookZalo' => $totalCustomerInteractEndFacebookZalo,
            'tyLeCustomerInteractFollowEndFacebookZalo' => $tyLeCustomerInteractEndFacebookZalo,

            'tyLeCustomerInteractFollowStartGoogle' => $tyLeCustomerInteractStartGoogle,
            'totalCustomerInteractFollowStartGoogle' => $totalCustomerInteractStartGoogle,
            'totalCustomerInteractFollowStartFacebookZalo' => $totalCustomerInteractStartFacebookZalo,
            'tyLeCustomerInteractFollowStartFacebookZalo' => $tyLeCustomerInteractStartFacebookZalo,
        ];
    }

    /*Tổng KH khong tương tác được*/
    private function totalCustomerNotInteract($resultStart, $resultEnd): array
    {
        $totalCustomerAll = $this->totalCustomerAll($resultStart, $resultEnd);
        $totalCustomerEndAll = $totalCustomerAll['totalCustomerEndAll'];
        $totalCustomerStartAll = $totalCustomerAll['totalCustomerStartAll'];
        $totalCustomerFacebookZalo = $this->totalCustomerFacebookZalo($resultStart, $resultEnd);
        $totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloStartAll'];
        $totalCustomerFacebookZaloEndAll = $totalCustomerFacebookZalo['totalCustomerFacebookZaloEndAll'];
        $totalCustomerGoogle = $this->totalCustomerGoogle($resultStart, $resultEnd);

        $totalCustomerGoogleStartAll = $totalCustomerGoogle['totalCustomerGoogleStartAll'];
        $totalCustomerGoogleEndAll = $totalCustomerGoogle['totalCustomerGoogleEndAll'];

        /* Tuần tham chiếu */
        $totalCustomerNotInteractStartAll = 0;

        $totalCustomerNotInteractStartAll += $resultStart['total_customer_texted_not_respond'];
        $totalCustomerNotInteractStartAll += $resultStart['total_customer_calling_not_answer_facebook'];
        $totalCustomerNotInteractStartAll += $resultStart['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractStartAll = (int)$totalCustomerStartAll > 0 ? round(
            ($totalCustomerNotInteractStartAll * 100) / $totalCustomerStartAll
        ) : 0;

        $totalCustomerNotInteractStartFacebookZalo = 0;
        $totalCustomerNotInteractStartFacebookZalo += $resultStart['total_customer_texted_not_respond'];
        $totalCustomerNotInteractStartFacebookZalo += $resultStart['total_customer_calling_not_answer_facebook'];

        $tyLeCustomerNotInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerNotInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;
        $totalCustomerNotInteractStartGoogle = 0;
        $totalCustomerNotInteractStartGoogle += $resultStart['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerNotInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        /* Tuần xem */
        $totalCustomerNotInteractEndAll = 0;

        $totalCustomerNotInteractEndAll += $resultEnd['total_customer_texted_not_respond'];
        $totalCustomerNotInteractEndAll += $resultEnd['total_customer_calling_not_answer_facebook'];
        $totalCustomerNotInteractEndAll += $resultEnd['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractEndAll = $totalCustomerEndAll > 0 ? round(
            ($totalCustomerNotInteractEndAll * 100) / $totalCustomerEndAll
        ) : 0;

        $totalCustomerNotInteractEndFacebookZalo = 0;
        $totalCustomerNotInteractEndFacebookZalo += $resultEnd['total_customer_texted_not_respond'];
        $totalCustomerNotInteractEndFacebookZalo += $resultEnd['total_customer_calling_not_answer_facebook'];

        $tyLeCustomerNotInteractEndFacebookZalo = $totalCustomerFacebookZaloEndAll > 0 ? round(
            ($totalCustomerNotInteractEndFacebookZalo * 100) / $totalCustomerFacebookZaloEndAll
        ) : 0;

        $totalCustomerNotInteractEndGoogle = 0;
        $totalCustomerNotInteractEndGoogle += $resultEnd['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractEndGoogle = $totalCustomerGoogleEndAll > 0 ? round(
            ($totalCustomerNotInteractEndGoogle * 100) / $totalCustomerGoogleEndAll
        ) : 0;

        $tyLeCustomerNotInteractAll = $tyLeCustomerNotInteractStartAll > 0 ? round(
            ($totalCustomerNotInteractEndAll - $tyLeCustomerNotInteractStartAll) * 100 /$tyLeCustomerNotInteractStartAll
        ) : 0;
        return [
            'tyLeCustomerNotInteractAll' => $tyLeCustomerNotInteractAll,

            'tyLeCustomerNotInteractEndGoogle' => $tyLeCustomerNotInteractEndGoogle,
            'totalCustomerNotInteractEndGoogle' => $totalCustomerNotInteractEndGoogle,
            'totalCustomerNotInteractEndFacebookZalo' => $totalCustomerNotInteractEndFacebookZalo,
            'tyLeCustomerNotInteractEndFacebookZalo' => $tyLeCustomerNotInteractEndFacebookZalo,
            'totalCustomerNotInteractEndAll' => $totalCustomerNotInteractEndAll,
            'tyLeCustomerNotInteractEndAll' => $tyLeCustomerNotInteractEndAll,

            'totalCustomerNotInteractStartAll' => $totalCustomerNotInteractStartAll,
            'tyLeCustomerNotInteractStartAll' => $tyLeCustomerNotInteractStartAll,
            'tyLeCustomerNotInteractStartGoogle' => $tyLeCustomerNotInteractStartGoogle,
            'totalCustomerNotInteractStartGoogle' => $totalCustomerNotInteractStartGoogle,
            'totalCustomerNotInteractStartFacebookZalo' => $totalCustomerNotInteractStartFacebookZalo,
            'tyLeCustomerNotInteractStartFacebookZalo' => $tyLeCustomerNotInteractStartFacebookZalo,
        ];
    }

}
