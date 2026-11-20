<?php

namespace App\Services\Reports;

class ReportControlMarketing
{
    protected $resultStart;
    protected $totalCustomerStartAll;
    protected $totalCustomerFacebookZaloStartAll;
    protected $totalCustomerGoogleStartAll;
    protected $totalCustomerInteractStartAll;
    protected $tyLeCustomerInteractStartAll;
    protected $tyLeCustomerInteractStartGoogle;
    protected $totalCustomerInteractStartGoogle;
    protected $totalCustomerInteractStartFacebookZalo;
    protected $tyLeCustomerInteractStartFacebookZalo;
    protected $tyLeCustomerInteractNotNeedStartGoogle;
    protected $totalCustomerInteractNotNeedStartGoogle;
    protected $totalCustomerInteractNotNeedStartFacebookZalo;
    protected $tyLeCustomerInteractNotNeedStartFacebookZalo;
    protected $tyLeCustomerInteractImmediateNotNeedStartGoogle;
    protected $totalCustomerInteractImmediateNotNeedStartGoogle;
    protected $totalCustomerInteractImmediateNotNeedStartFacebookZalo;
    protected $tyLeCustomerInteractImmediateNotNeedStartFacebookZalo;
    protected $tyLeCustomerInteractNotImportedStartGoogle;
    protected $totalCustomerInteractNotImportedStartGoogle;
    protected $totalCustomerInteractNotImportedStartFacebookZalo;
    protected $tyLeCustomerInteractNotImportedStartFacebookZalo;
    protected $tyLeCustomerInteractConsignmentStartGoogle;
    protected $totalCustomerInteractConsignmentStartGoogle;
    protected $totalCustomerInteractConsignmentStartFacebookZalo;
    protected $tyLeCustomerInteractConsignmentStartFacebookZalo;
    protected $tyLeCustomerInteractOrderBusinessStartGoogle;
    protected $totalCustomerInteractOrderBusinessStartGoogle;
    protected $totalCustomerInteractOrderBusinessStartFacebookZalo;
    protected $tyLeCustomerInteractOrderBusinessStartFacebookZalo;
    protected $tyLeCustomerInteractFollowStartGoogle;
    protected $totalCustomerInteractFollowStartGoogle;
    protected $totalCustomerInteractFollowStartFacebookZalo;
    protected $tyLeCustomerInteractFollowStartFacebookZalo;
    protected $totalCustomerNotInteractStartAll;
    protected $tyLeCustomerNotInteractStartAll;
    protected $tyLeCustomerNotInteractStartGoogle;
    protected $totalCustomerNotInteractStartGoogle;
    protected $totalCustomerNotInteractStartFacebookZalo;
    protected $tyLeCustomerNotInteractStartFacebookZalo;
    protected $totalCustomerNotInteractTextedNotRespondStartFacebookZalo;
    protected $tyLeCustomerNotInteractTextedNotRespondStartFacebookZalo;
    protected $totalCustomerNotInteractCallingNotAnswerStartFacebookZalo;
    protected $tyLeCustomerNotInteractCallingNotAnswerStartFacebookZalo;
    protected $totalCustomerNotInteractCallingNotAnswerStartGoogle;
    protected $tyLeCustomerNotInteractCallingNotAnswerStartGoogle;
    protected $totalCustomerPersonalBusinessStartAll;
    protected $totalCustomerPersonalStartAll;
    protected $tyLeCustomerPersonalStartAll;
    protected $totalCustomerPersonalBusinessFacebookZaloStartAll;
    protected $totalCustomerPersonalBusinessGoogleStartAll;
    protected $totalCustomerPersonalFacebookZaloStart;
    protected $tyLeCustomerPersonalFacebookZaloStart;
    protected $totalCustomerPersonalGoogleStart;
    protected $tyLeCustomerPersonalGoogleStart;
    protected $totalCustomerBusinessStartAll;
    protected $tyLeCustomerBusinessStartAll;
    protected $totalCustomerBusinessFacebookZaloStart;
    protected $tyLeCustomerBusinessFacebookZaloStart;
    protected $totalCustomerBusinessGoogleStart;
    protected $tyLeCustomerBusinessGoogleStart;
    protected $tyLeCustomerInteractOrderPersonalStartGoogle;
    protected $totalCustomerInteractOrderPersonalStartGoogle;
    protected $totalCustomerInteractOrderPersonalStartFacebookZalo;
    protected $tyLeCustomerInteractOrderPersonalStartFacebookZalo;

    function __construct($resultStart) {
        $this->resultStart = $resultStart;
    }
    /* lấy all khách hàng*/
    public function totalCustomerAll(): ReportControlMarketing
    {
        $totalCustomerStartAll = 0;
        $totalCustomerStartAll += $this->resultStart['total_customer_texted_not_respond'];
        $totalCustomerStartAll += $this->resultStart['total_customer_calling_not_answer_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_calling_not_answer_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_follow_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_follow_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_personal_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_business_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_personal_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_order_business_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_consignment_advise_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerStartAll += $this->resultStart['total_customer_not_need_google'];
        $totalCustomerStartAll += $this->resultStart['total_customer_not_need_facebook'];
        $this->totalCustomerStartAll =$totalCustomerStartAll;
        return $this;
    }
    /* lấy all khách hàng zalo facebook*/
    public function totalCustomerFacebookZalo(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloStartAll = 0;
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_texted_not_respond'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_calling_not_answer_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_order_follow_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_order_personal_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_order_business_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerFacebookZaloStartAll += $this->resultStart['total_customer_not_need_facebook'];

        $this->totalCustomerFacebookZaloStartAll = $totalCustomerFacebookZaloStartAll;
        return $this;
    }
   /* lấy all khách hàng google*/
    public function totalCustomerGoogle(): ReportControlMarketing
    {
        $totalCustomerGoogleStartAll = 0;
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_calling_not_answer_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_order_follow_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_order_personal_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_order_business_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_consignment_advise_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerGoogleStartAll += $this->resultStart['total_customer_not_need_google'];

        $this->totalCustomerGoogleStartAll = $totalCustomerGoogleStartAll;
        return $this;
    }

    /*Tổng KH tương tác được*/
    public function totalCustomerInteract(): ReportControlMarketing
    {
        $totalCustomerStartAll = $this->totalCustomerStartAll;
        $totalCustomerFacebookZaloStartAll = $this->totalCustomerFacebookZaloStartAll;
        $totalCustomerGoogleStartAll = $this->totalCustomerGoogleStartAll;

        /* Tuần tham chiếu */
        $totalCustomerInteractStartAll = 0;
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_follow_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_follow_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_personal_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_business_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_personal_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_order_business_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_consignment_advise_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_not_need_google'];
        $totalCustomerInteractStartAll += $this->resultStart['total_customer_not_need_facebook'];
        $tyLeCustomerInteractStartAll = (int)$totalCustomerStartAll > 0 ? round(
            ($totalCustomerInteractStartAll * 100) / $totalCustomerStartAll
        ) : 0;
        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_follow_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_personal_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_business_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_consignment_advise_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_need_but_item_not_imported_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_not_immediate_need_facebook'];
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_not_need_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;
        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_follow_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_personal_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_business_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_consignment_advise_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_need_but_item_not_imported_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_not_immediate_need_google'];
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_not_need_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        $this->totalCustomerInteractStartAll = $totalCustomerInteractStartAll;
        $this->tyLeCustomerInteractStartAll = $tyLeCustomerInteractStartAll;
        $this->tyLeCustomerInteractStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }

    /*KH không có nhu cầu*/
    public function totalCustomerInteractNotNeed(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;
        /*Tuần tham chiếu*/
        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_not_need_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;
        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_not_need_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractNotNeedStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractNotNeedStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractNotNeedStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractNotNeedStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;
        return $this;
    }

    /*KH Tham khảo chưa có nhu cầu ngay*/
    public function totalCustomerInteractNotImmediateNeed(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_not_immediate_need_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_not_immediate_need_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractImmediateNotNeedStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractImmediateNotNeedStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractImmediateNotNeedStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractImmediateNotNeedStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }
    /*Khach hang co nhu cau nhung mat hang k nhap duoc*/
    public function totalCustomerInteractNotImported(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;

        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_need_but_item_not_imported_facebook'];

        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_need_but_item_not_imported_google'];

        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractNotImportedStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractNotImportedStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractNotImportedStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractNotImportedStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;

    }

    /*KH tư vấn ký gửi*/
    public function totalCustomerInteractConsignment(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;
        /* Tuần tham chiếu */
        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_consignment_advise_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_consignment_advise_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractConsignmentStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractConsignmentStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractConsignmentStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractConsignmentStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }

    /*Tổng KH lên đơn kinh doanh*/
    public function totalCustomerInteractOrderBusiness(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;
        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_business_facebook'];;
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_business_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractOrderBusinessStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractOrderBusinessStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractOrderBusinessStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractOrderBusinessStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }

    /*Tổng KH lên đơn tiêu dùng*/
    public function totalCustomerInteractOrderPersonal(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;
        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_personal_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_personal_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractOrderPersonalStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractOrderPersonalStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractOrderPersonalStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractOrderPersonalStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }

    /*Tổng KH follow len don*/
    public function totalCustomerInteractFollow(): ReportControlMarketing
    {
        $totalCustomerFacebookZaloInteractStartAll = $this->totalCustomerInteractStartFacebookZalo;
        $totalCustomerGoogleInteractStartAll = $this->totalCustomerInteractStartGoogle;
        /* Tuần tham chiếu */

        $totalCustomerInteractStartFacebookZalo = 0;
        $totalCustomerInteractStartFacebookZalo += $this->resultStart['total_customer_order_follow_facebook'];
        $tyLeCustomerInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloInteractStartAll
        ) : 0;

        $totalCustomerInteractStartGoogle = 0;
        $totalCustomerInteractStartGoogle += $this->resultStart['total_customer_order_follow_google'];
        $tyLeCustomerInteractStartGoogle = (int)$totalCustomerGoogleInteractStartAll > 0 ? round(
            ($totalCustomerInteractStartGoogle * 100) / $totalCustomerGoogleInteractStartAll
        ) : 0;

        $this->tyLeCustomerInteractFollowStartGoogle = $tyLeCustomerInteractStartGoogle;
        $this->totalCustomerInteractFollowStartGoogle = $totalCustomerInteractStartGoogle;
        $this->totalCustomerInteractFollowStartFacebookZalo = $totalCustomerInteractStartFacebookZalo;
        $this->tyLeCustomerInteractFollowStartFacebookZalo = $tyLeCustomerInteractStartFacebookZalo;

        return $this;
    }

    /*Tổng KH khong tương tác được*/
    public function totalCustomerNotInteract(): ReportControlMarketing
    {
        $totalCustomerStartAll = $this->totalCustomerStartAll;
        $totalCustomerFacebookZaloStartAll = $this->totalCustomerFacebookZaloStartAll;
        $totalCustomerGoogleStartAll = $this->totalCustomerGoogleStartAll;

        /* Tuần tham chiếu */
        $totalCustomerNotInteractStartAll = 0;

        $totalCustomerNotInteractStartAll += $this->resultStart['total_customer_texted_not_respond'];
        $totalCustomerNotInteractStartAll += $this->resultStart['total_customer_calling_not_answer_facebook'];
        $totalCustomerNotInteractStartAll += $this->resultStart['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractStartAll = (int)$totalCustomerStartAll > 0 ? round(
            ($totalCustomerNotInteractStartAll * 100) / $totalCustomerStartAll
        ) : 0;

        $totalCustomerNotInteractStartFacebookZalo = 0;
        $totalCustomerNotInteractStartFacebookZalo += $this->resultStart['total_customer_texted_not_respond'];
        $totalCustomerNotInteractStartFacebookZalo += $this->resultStart['total_customer_calling_not_answer_facebook'];

        $tyLeCustomerNotInteractStartFacebookZalo = (int)$totalCustomerFacebookZaloStartAll > 0 ? round(
            ($totalCustomerNotInteractStartFacebookZalo * 100) / $totalCustomerFacebookZaloStartAll
        ) : 0;
        $totalCustomerNotInteractStartGoogle = 0;
        $totalCustomerNotInteractStartGoogle += $this->resultStart['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractStartGoogle = (int)$totalCustomerGoogleStartAll > 0 ? round(
            ($totalCustomerNotInteractStartGoogle * 100) / $totalCustomerGoogleStartAll
        ) : 0;

        $this->totalCustomerNotInteractStartAll = $totalCustomerNotInteractStartAll;
        $this->tyLeCustomerNotInteractStartAll = $tyLeCustomerNotInteractStartAll;
        $this->tyLeCustomerNotInteractStartGoogle = $tyLeCustomerNotInteractStartGoogle;
        $this->totalCustomerNotInteractStartGoogle = $totalCustomerNotInteractStartGoogle;
        $this->totalCustomerNotInteractStartFacebookZalo = $totalCustomerNotInteractStartFacebookZalo;
        $this->tyLeCustomerNotInteractStartFacebookZalo = $tyLeCustomerNotInteractStartFacebookZalo;

        return $this;
    }

    /*KH nhắn tin không phản hồi*/
    public function totalCustomerNotInteractTextedNotRespond(): ReportControlMarketing
    {
        $totalCustomerNotInteractStartFacebookZalo = $this->totalCustomerNotInteractStartFacebookZalo;
        /* Tuần tham chiếu */

        $totalCustomerNotInteractTextedNotRespondStartFacebookZalo = 0;
        $totalCustomerNotInteractTextedNotRespondStartFacebookZalo += $this->resultStart['total_customer_texted_not_respond'];

        $tyLeCustomerNotInteractTextedNotRespondStartFacebookZalo = (int)$totalCustomerNotInteractStartFacebookZalo > 0 ? round(
            ($totalCustomerNotInteractTextedNotRespondStartFacebookZalo * 100) / $totalCustomerNotInteractStartFacebookZalo
        ) : 0;

        /* Tuần xem */
        $this->totalCustomerNotInteractTextedNotRespondStartFacebookZalo = $totalCustomerNotInteractTextedNotRespondStartFacebookZalo;
        $this->tyLeCustomerNotInteractTextedNotRespondStartFacebookZalo = $tyLeCustomerNotInteractTextedNotRespondStartFacebookZalo;

        return $this;
    }

    /*KH nhắn tin không phản hồi*/
    public function totalCustomerNotInteractCallingNotAnswer(): ReportControlMarketing
    {
        $totalCustomerNotInteractStartFacebookZalo = $this->totalCustomerNotInteractStartFacebookZalo;
        $totalCustomerNotInteractStartGoogle = $this->totalCustomerNotInteractStartGoogle;
        /* Tuần tham chiếu */

        $totalCustomerNotInteractCallingNotAnswerStartFacebookZalo = 0;
        $totalCustomerNotInteractCallingNotAnswerStartFacebookZalo += $this->resultStart['total_customer_calling_not_answer_facebook'];

        $tyLeCustomerNotInteractCallingNotAnswerStartFacebookZalo = (int)$totalCustomerNotInteractStartFacebookZalo > 0 ? round(
            ($totalCustomerNotInteractCallingNotAnswerStartFacebookZalo * 100) / $totalCustomerNotInteractStartFacebookZalo
        ) : 0;

        $totalCustomerNotInteractCallingNotAnswerStartGoogle = 0;
        $totalCustomerNotInteractCallingNotAnswerStartGoogle += $this->resultStart['total_customer_calling_not_answer_google'];

        $tyLeCustomerNotInteractCallingNotAnswerStartGoogle = (int)$totalCustomerNotInteractStartGoogle > 0 ? round(
            ($totalCustomerNotInteractCallingNotAnswerStartGoogle * 100) / $totalCustomerNotInteractStartGoogle
        ) : 0;

        $this->totalCustomerNotInteractCallingNotAnswerStartFacebookZalo = $totalCustomerNotInteractCallingNotAnswerStartFacebookZalo;
        $this->tyLeCustomerNotInteractCallingNotAnswerStartFacebookZalo = $tyLeCustomerNotInteractCallingNotAnswerStartFacebookZalo;
        $this->totalCustomerNotInteractCallingNotAnswerStartGoogle = $totalCustomerNotInteractCallingNotAnswerStartGoogle;
        $this->tyLeCustomerNotInteractCallingNotAnswerStartGoogle = $tyLeCustomerNotInteractCallingNotAnswerStartGoogle;

        return $this;
    }
   /* khách hàng tiêu dùng*/

    /* tổng khách tiêu dùng và kinh doanh*/
    public function totalCustomerPersonalBusiness(): ReportControlMarketing
    {
        /*tong all facebook and google*/
        $totalCustomerPersonalBusinessStartAll = 0;
        $totalCustomerPersonalBusinessStartAll += $this->resultStart['total_customer_business_facebook'];
        $totalCustomerPersonalBusinessStartAll += $this->resultStart['total_customer_personal_facebook'];
        $totalCustomerPersonalBusinessStartAll += $this->resultStart['total_customer_business_google'];
        $totalCustomerPersonalBusinessStartAll += $this->resultStart['total_customer_personal_google'];
        /*tong all facebook*/
        $totalCustomerPersonalBusinessFacebookZaloStartAll = 0;
        $totalCustomerPersonalBusinessFacebookZaloStartAll += $this->resultStart['total_customer_business_facebook'];
        $totalCustomerPersonalBusinessFacebookZaloStartAll += $this->resultStart['total_customer_personal_facebook'];
        /*tong all gg*/
        $totalCustomerPersonalBusinessGoogleStartAll = 0;
        $totalCustomerPersonalBusinessGoogleStartAll += $this->resultStart['total_customer_business_google'];
        $totalCustomerPersonalBusinessGoogleStartAll += $this->resultStart['total_customer_personal_google'];

        $this->totalCustomerPersonalBusinessStartAll = $totalCustomerPersonalBusinessStartAll;
        $this->totalCustomerPersonalBusinessFacebookZaloStartAll = $totalCustomerPersonalBusinessFacebookZaloStartAll;
        $this->totalCustomerPersonalBusinessGoogleStartAll = $totalCustomerPersonalBusinessGoogleStartAll;

        return $this;
    }

    /*Tổng khách tiêu dùng*/
    public function totalCustomerPersonal(): ReportControlMarketing
    {
        $totalCustomerPersonalBusinessStartAll = $this->totalCustomerPersonalBusinessStartAll;
        /*Tuan tham chieu*/
        $totalCustomerPersonalStartAll = 0;
        $totalCustomerPersonalStartAll += $this->resultStart['total_customer_personal_facebook'];
        $totalCustomerPersonalStartAll += $this->resultStart['total_customer_personal_google'];
        $tyLeCustomerPersonalStartAll = $totalCustomerPersonalBusinessStartAll > 0 ?
            round($totalCustomerPersonalStartAll*100/$totalCustomerPersonalBusinessStartAll) : 0;
        /*Tuan tham chieu*/

        $totalCustomerPersonalBusinessFacebookZaloStartAll = $this->totalCustomerPersonalBusinessFacebookZaloStartAll;

        $totalCustomerPersonalFacebookZaloStart = 0;
        $totalCustomerPersonalFacebookZaloStart += $this->resultStart['total_customer_personal_facebook'];
        $tyLeCustomerPersonalFacebookZaloStart = $totalCustomerPersonalBusinessFacebookZaloStartAll > 0 ? round(
            $totalCustomerPersonalFacebookZaloStart*100/$totalCustomerPersonalBusinessFacebookZaloStartAll
        ) : 0;

        $totalCustomerPersonalBusinessGoogleStartAll = $this->totalCustomerPersonalBusinessGoogleStartAll;

        $totalCustomerPersonalGoogleStart = 0;
        $totalCustomerPersonalGoogleStart += $this->resultStart['total_customer_personal_google'];
        $tyLeCustomerPersonalGoogleStart = $totalCustomerPersonalBusinessGoogleStartAll > 0 ? round(
            $totalCustomerPersonalGoogleStart*100/$totalCustomerPersonalBusinessGoogleStartAll
        ) : 0;

        $this->totalCustomerPersonalStartAll = $totalCustomerPersonalStartAll;
        $this->tyLeCustomerPersonalStartAll = $tyLeCustomerPersonalStartAll;
        $this->totalCustomerPersonalFacebookZaloStart = $totalCustomerPersonalFacebookZaloStart;
        $this->tyLeCustomerPersonalFacebookZaloStart = $tyLeCustomerPersonalFacebookZaloStart;
        $this->totalCustomerPersonalGoogleStart = $totalCustomerPersonalGoogleStart;
        $this->tyLeCustomerPersonalGoogleStart = $tyLeCustomerPersonalGoogleStart;

        return $this;
    }

    /*Tổng khách kinh doanh*/

    public function totalCustomerBusiness(): ReportControlMarketing
    {
        $totalCustomerPersonalBusinessStartAll = $this->totalCustomerPersonalBusinessStartAll;
        /*Tuan tham chieu*/
        $totalCustomerBusinessStartAll = 0;
        $totalCustomerBusinessStartAll += $this->resultStart['total_customer_business_facebook'];
        $totalCustomerBusinessStartAll += $this->resultStart['total_customer_business_google'];
        $tyLeCustomerBusinessStartAll = $totalCustomerPersonalBusinessStartAll > 0 ?
            round($totalCustomerBusinessStartAll*100/$totalCustomerPersonalBusinessStartAll) : 0;
        /*Tuan xem*/
        /*Tuan tham chieu*/

        $totalCustomerPersonalBusinessFacebookZaloStartAll = $this->totalCustomerPersonalBusinessFacebookZaloStartAll;

        $totalCustomerBusinessFacebookZaloStart = 0;
        $totalCustomerBusinessFacebookZaloStart += $this->resultStart['total_customer_business_facebook'];
        $tyLeCustomerBusinessFacebookZaloStart = $totalCustomerPersonalBusinessFacebookZaloStartAll > 0 ? round(
            $totalCustomerBusinessFacebookZaloStart*100/$totalCustomerPersonalBusinessFacebookZaloStartAll
        ) : 0;

        $totalCustomerPersonalBusinessGoogleStartAll = $this->totalCustomerPersonalBusinessGoogleStartAll;

        $totalCustomerBusinessGoogleStart = 0;
        $totalCustomerBusinessGoogleStart += $this->resultStart['total_customer_business_google'];
        $tyLeCustomerBusinessGoogleStart = $totalCustomerPersonalBusinessGoogleStartAll > 0 ? round(
            $totalCustomerBusinessGoogleStart*100/$totalCustomerPersonalBusinessGoogleStartAll
        ) : 0;
        $this->totalCustomerBusinessStartAll = $totalCustomerBusinessStartAll;
        $this->tyLeCustomerBusinessStartAll = $tyLeCustomerBusinessStartAll;
        $this->totalCustomerBusinessFacebookZaloStart = $totalCustomerBusinessFacebookZaloStart;
        $this->tyLeCustomerBusinessFacebookZaloStart = $tyLeCustomerBusinessFacebookZaloStart;
        $this->totalCustomerBusinessGoogleStart = $totalCustomerBusinessGoogleStart;
        $this->tyLeCustomerBusinessGoogleStart = $tyLeCustomerBusinessGoogleStart;

        return $this;
    }

    public function exec(): \stdClass
    {
        $results = new \stdClass();

        $results->totalCustomerBusinessFacebookZalo = $this->totalCustomerBusinessFacebookZaloStart;
        $results->tyLeCustomerBusinessFacebookZalo = $this->tyLeCustomerBusinessFacebookZaloStart;
        $results->totalCustomerBusinessGoogle = $this->totalCustomerBusinessGoogleStart;
        $results->tyLeCustomerBusinessGoogle = $this->tyLeCustomerBusinessGoogleStart;
        $results->totalCustomerBusinessAll = $this->totalCustomerBusinessStartAll;
        $results->tyLeCustomerBusinessAll = $this->tyLeCustomerBusinessStartAll;

        $results->totalCustomerPersonalAll = $this->totalCustomerPersonalStartAll;
        $results->tyLeCustomerPersonalAll = $this->tyLeCustomerPersonalStartAll;
        $results->totalCustomerPersonalFacebookZalo = $this->totalCustomerPersonalFacebookZaloStart;
        $results->tyLeCustomerPersonalFacebookZalo = $this->tyLeCustomerPersonalFacebookZaloStart;
        $results->totalCustomerPersonalGoogle = $this->totalCustomerPersonalGoogleStart;
        $results->tyLeCustomerPersonalGoogle = $this->tyLeCustomerPersonalGoogleStart;

        $results->totalCustomerPersonalBusinessAll = $this->totalCustomerPersonalBusinessStartAll;
        $results->totalCustomerPersonalBusinessFacebookZaloAll = $this->totalCustomerPersonalBusinessFacebookZaloStartAll;
        $results->totalCustomerPersonalBusinessGoogleAll = $this->totalCustomerPersonalBusinessGoogleStartAll;

        $results->totalCustomerNotInteractCallingNotAnswerFacebookZalo = $this->totalCustomerNotInteractCallingNotAnswerStartFacebookZalo;
        $results->tyLeCustomerNotInteractCallingNotAnswerFacebookZalo = $this->tyLeCustomerNotInteractCallingNotAnswerStartFacebookZalo;
        $results->totalCustomerNotInteractCallingNotAnswerGoogle = $this->totalCustomerNotInteractCallingNotAnswerStartGoogle;
        $results->tyLeCustomerNotInteractCallingNotAnswerGoogle = $this->tyLeCustomerNotInteractCallingNotAnswerStartGoogle;

        $results->totalCustomerNotInteractTextedNotRespondFacebookZalo = $this->totalCustomerNotInteractTextedNotRespondStartFacebookZalo;
        $results->tyLeCustomerNotInteractTextedNotRespondFacebookZalo = $this->tyLeCustomerNotInteractTextedNotRespondStartFacebookZalo;

        $results->totalCustomerNotInteractAll = $this->totalCustomerNotInteractStartAll;
        $results->tyLeCustomerNotInteractAll = $this->tyLeCustomerNotInteractStartAll;
        $results->tyLeCustomerNotInteractGoogle = $this->tyLeCustomerNotInteractStartGoogle;
        $results->totalCustomerNotInteractGoogle = $this->totalCustomerNotInteractStartGoogle;
        $results->totalCustomerNotInteractFacebookZalo = $this->totalCustomerNotInteractStartFacebookZalo;
        $results->tyLeCustomerNotInteractFacebookZalo = $this->tyLeCustomerNotInteractStartFacebookZalo;

        $results->tyLeCustomerInteractFollowGoogle = $this->tyLeCustomerInteractFollowStartGoogle;
        $results->totalCustomerInteractFollowGoogle = $this->totalCustomerInteractFollowStartGoogle;
        $results->totalCustomerInteractFollowFacebookZalo = $this->totalCustomerInteractFollowStartFacebookZalo;
        $results->tyLeCustomerInteractFollowFacebookZalo = $this->tyLeCustomerInteractFollowStartFacebookZalo;

        $results->tyLeCustomerInteractOrderPersonalGoogle = $this->tyLeCustomerInteractOrderPersonalStartGoogle;
        $results->totalCustomerInteractOrderPersonalGoogle = $this->totalCustomerInteractOrderPersonalStartGoogle;
        $results->totalCustomerInteractOrderPersonalFacebookZalo = $this->totalCustomerInteractOrderPersonalStartFacebookZalo;
        $results->tyLeCustomerInteractOrderPersonalFacebookZalo = $this->tyLeCustomerInteractOrderPersonalStartFacebookZalo;

        $results->tyLeCustomerInteractOrderBusinessGoogle = $this->tyLeCustomerInteractOrderBusinessStartGoogle;
        $results->totalCustomerInteractOrderBusinessGoogle = $this->totalCustomerInteractOrderBusinessStartGoogle;
        $results->totalCustomerInteractOrderBusinessFacebookZalo = $this->totalCustomerInteractOrderBusinessStartFacebookZalo;
        $results->tyLeCustomerInteractOrderBusinessFacebookZalo = $this->tyLeCustomerInteractOrderBusinessStartFacebookZalo;

        $results->tyLeCustomerInteractConsignmentGoogle = $this->tyLeCustomerInteractConsignmentStartGoogle;
        $results->totalCustomerInteractConsignmentGoogle = $this->totalCustomerInteractConsignmentStartGoogle;
        $results->totalCustomerInteractConsignmentFacebookZalo = $this->totalCustomerInteractConsignmentStartFacebookZalo;
        $results->tyLeCustomerInteractConsignmentFacebookZalo = $this->tyLeCustomerInteractConsignmentStartFacebookZalo;

        $results->tyLeCustomerInteractNotImportedGoogle = $this->tyLeCustomerInteractNotImportedStartGoogle;
        $results->totalCustomerInteractNotImportedGoogle = $this->totalCustomerInteractNotImportedStartGoogle;
        $results->totalCustomerInteractNotImportedFacebookZalo = $this->totalCustomerInteractNotImportedStartFacebookZalo;
        $results->tyLeCustomerInteractNotImportedFacebookZalo = $this->tyLeCustomerInteractNotImportedStartFacebookZalo;

        $results->tyLeCustomerInteractImmediateNotNeedGoogle = $this->tyLeCustomerInteractImmediateNotNeedStartGoogle;
        $results->totalCustomerInteractImmediateNotNeedGoogle = $this->totalCustomerInteractImmediateNotNeedStartGoogle;
        $results->totalCustomerInteractImmediateNotNeedFacebookZalo = $this->totalCustomerInteractImmediateNotNeedStartFacebookZalo;
        $results->tyLeCustomerInteractImmediateNotNeedFacebookZalo = $this->tyLeCustomerInteractImmediateNotNeedStartFacebookZalo;

        $results->tyLeCustomerInteractNotNeedGoogle = $this->tyLeCustomerInteractNotNeedStartGoogle;
        $results->totalCustomerInteractNotNeedGoogle = $this->totalCustomerInteractNotNeedStartGoogle;
        $results->totalCustomerInteractNotNeedFacebookZalo = $this->totalCustomerInteractNotNeedStartFacebookZalo;
        $results->tyLeCustomerInteractNotNeedFacebookZalo = $this->tyLeCustomerInteractNotNeedStartFacebookZalo;

        $results->totalCustomerInteractAll = $this->totalCustomerInteractStartAll;
        $results->tyLeCustomerInteractAll = $this->tyLeCustomerInteractStartAll;
        $results->tyLeCustomerInteractGoogle = $this->tyLeCustomerInteractStartGoogle;
        $results->totalCustomerInteractGoogle = $this->totalCustomerInteractStartGoogle;
        $results->totalCustomerInteractFacebookZalo = $this->totalCustomerInteractStartFacebookZalo;
        $results->tyLeCustomerInteractFacebookZalo = $this->tyLeCustomerInteractStartFacebookZalo;

        $results->totalCustomerGoogleAll = $this->totalCustomerGoogleStartAll;

        $results->totalCustomerFacebookZaloAll = $this->totalCustomerFacebookZaloStartAll;

        $results->totalCustomerAll = $this->totalCustomerStartAll;

        return $results;
    }
}
