<?php

namespace App\Services\Reports;

class ReportCustomerOrderSale
{
    /**
     * @var mixed
     */
    protected $result;
    protected $total_customer_consignment_advise;
    protected $total_customer_order_advise;
    protected $total_customer_consignment;
    protected $total_customer_order_first;
    protected $total_customer_order_first_google_ads_personal;
    protected $total_customer_order_first_google_ads_business;
    protected $total_customer_order_first_facebook_ads_personal;
    protected $total_customer_order_first_facebook_ads_business;
    protected $total_amount_first;
    protected $total_amount_first_google_ads_personal;
    protected $total_amount_first_google_ads_business;
    protected $total_amount_first_facebook_ads_personal;
    protected $total_amount_first_facebook_ads_business;
    protected $total_service_charge_first;
    protected $total_service_charge_first_google_ads_personal;
    protected $total_service_charge_first_google_ads_business;
    protected $total_service_charge_first_facebook_ads_personal;
    protected $total_service_charge_first_facebook_ads_business;
    protected $total_customer_order_second_google_ads;
    protected $total_customer_order_second_facebook_ads;
    protected $total_amount_second;
    protected $total_service_charge_second;

    function __construct($result)
    {
        $this->result = $result;
    }
    /* khách hàng tư vấn ký gửi */
    public function getCustomerConsignmentAdvise(): ReportCustomerOrderSale
    {
        $total_customer_consignment_advise = $this->result->sum('total_customer_consignment_advise') ?? 0;
        $this->total_customer_consignment_advise = $total_customer_consignment_advise;
        return $this;
    }

    /* khách hàng tư vấn đặt hàng */
    public function getCustomerOrderAdvise(): ReportCustomerOrderSale
    {
        $total_customer_order_advise = $this->result->sum('total_customer_order_advise') ?? 0;
        $this->total_customer_order_advise = $total_customer_order_advise;
        return $this;
    }
    /* Khach hang dat hang ky gui */
    public function customerConsignment(): ReportCustomerOrderSale
    {
        $total_customer_consignment = $this->result->sum('total_customer_consignment') ?? 0;
        $this->total_customer_consignment = $total_customer_consignment;
        return $this;
    }

    /* Khach hang dat don dau tien*/

    public function customerOrderFirst(): ReportCustomerOrderSale
    {
        $total_customer_order_first = $this->result->sum('total_customer_order_first') ?? 0;
        $this->total_customer_order_first = $total_customer_order_first;
        return $this;
    }

    /*KH đặt hàng đơn đầu tiên từ GG ads personal*/
    public function customerOrderFirstGooglePersonal(): ReportCustomerOrderSale
    {
        $total_customer_order_first_google_ads_personal = $this->result->sum('total_customer_order_first_gg_ads_personal') ?? 0;
        $this->total_customer_order_first_google_ads_personal = $total_customer_order_first_google_ads_personal;
        return $this;
    }

    /*KH đặt hàng đơn đầu tiên từ GG ads business*/
    public function customerOrderFirstGoogleBusiness(): ReportCustomerOrderSale
    {
        $total_customer_order_first_google_ads_business = $this->result->sum('total_customer_order_first_gg_ads_business') ?? 0;
        $this->total_customer_order_first_google_ads_business = $total_customer_order_first_google_ads_business;
        return $this;
    }

    /*KH đặt hàng đơn đầu tiên từ FB ads personal*/
    public function customerOrderFirstFacebookPersonal(): ReportCustomerOrderSale
    {
        $total_customer_order_first_facebook_ads_personal = $this->result->sum('total_customer_order_first_fb_ads_personal') ?? 0;
        $this->total_customer_order_first_facebook_ads_personal = $total_customer_order_first_facebook_ads_personal;
        return $this;
    }

    /*KH đặt hàng đơn đầu tiên từ FB ads business*/
    public function customerOrderFirstFacebookBusiness(): ReportCustomerOrderSale
    {
        $total_customer_order_first_facebook_ads_business = $this->result->sum('total_customer_order_first_fb_ads_business') ?? 0;
        $this->total_customer_order_first_facebook_ads_business = $total_customer_order_first_facebook_ads_business;
        return $this;
    }
    /*Giá trị đơn hàng đầu tiên*/
    public function amountFirst(): ReportCustomerOrderSale
    {
        $total_amount_first = $this->result->sum('total_amount_first') ?? 0;
        $this->total_amount_first = $total_amount_first;
        return $this;
    }

    /*Giá trị hàng đơn đầu tiên từ GG ads personal*/
    public function amountFirstGooglePersonal(): ReportCustomerOrderSale
    {
        $total_amount_first_google_ads_personal = $this->result->sum('total_amount_first_gg_ads_personal') ?? 0;
        $this->total_amount_first_google_ads_personal = $total_amount_first_google_ads_personal;
        return $this;
    }

    /*Giá trị hàng đơn đầu tiên từ GG ads business*/
    public function amountFirstGoogleBusiness(): ReportCustomerOrderSale
    {
        $total_amount_first_google_ads_business = $this->result->sum('total_amount_first_gg_ads_business') ?? 0;
        $this->total_amount_first_google_ads_business = $total_amount_first_google_ads_business;
        return $this;
    }

    /*Giá trị hàng đơn đầu tiên từ FB ads personal*/
    public function amountFirstFacebookPersonal(): ReportCustomerOrderSale
    {
        $total_amount_first_facebook_ads_personal = $this->result->sum('total_amount_first_fb_ads_personal') ?? 0;
        $this->total_amount_first_facebook_ads_personal = $total_amount_first_facebook_ads_personal;
        return $this;
    }

    /*Giá trị hàng đơn đầu tiên từ FB ads business*/
    public function amountFirstFacebookBusiness(): ReportCustomerOrderSale
    {
        $total_amount_first_facebook_ads_business = $this->result->sum('total_amount_first_fb_ads_business') ?? 0;
        $this->total_amount_first_facebook_ads_business = $total_amount_first_facebook_ads_business;
        return $this;
    }
    /*Phí dịch vụ đơn hàng đầu tiên*/
    public function serviceChargeFirst(): ReportCustomerOrderSale
    {
        $total_service_charge_first = $this->result->sum('total_service_charge_first') ?? 0;
        $this->total_service_charge_first = $total_service_charge_first;
        return $this;
    }

    /*Phí dịch vụ đơn hàng đầu tiên tu gg personal*/
    public function serviceChargeFirstGooglePersonal(): ReportCustomerOrderSale
    {
        $total_service_charge_first_google_ads_personal = $this->result->sum('total_service_charge_first_gg_ads_personal') ?? 0;
        $this->total_service_charge_first_google_ads_personal = $total_service_charge_first_google_ads_personal;
        return $this;
    }

    /*Phí dịch vụ đơn hàng đầu tiên tu gg business*/
    public function serviceChargeFirstGoogleBusiness(): ReportCustomerOrderSale
    {
        $total_service_charge_first_google_ads_business = $this->result->sum('total_service_charge_first_gg_ads_business') ?? 0;
        $this->total_service_charge_first_google_ads_business = $total_service_charge_first_google_ads_business;
        return $this;
    }

    /*Phí dịch vụ đơn hàng đầu tiên tu fb personal*/
    public function serviceChargeFirstFacebookPersonal(): ReportCustomerOrderSale
    {
        $total_service_charge_first_facebook_ads_personal = $this->result->sum('total_service_charge_first_fb_ads_personal') ?? 0;
        $this->total_service_charge_first_facebook_ads_personal = $total_service_charge_first_facebook_ads_personal;
        return $this;
    }

    /*Phí dịch vụ đơn hàng đầu tiên tu fb business*/
    public function serviceChargeFirstFacebookBusiness(): ReportCustomerOrderSale
    {
        $total_service_charge_first_facebook_ads_business = $this->result->sum('total_service_charge_first_fb_ads_business') ?? 0;
        $this->total_service_charge_first_facebook_ads_business = $total_service_charge_first_facebook_ads_business;
        return $this;
    }

    /*KH đặt từ đơn thứ 2 trở lên tù GG*/
    public function customerOrderSecondGoogle(): ReportCustomerOrderSale
    {
        $total_customer_order_second_google_ads = $this->result->sum('total_customer_order_second_gg_ads') ?? 0;
        $this->total_customer_order_second_google_ads = $total_customer_order_second_google_ads;
        return $this;
    }

    /*KH đặt từ đơn thứ 2 trở lên tù FB*/
    public function customerOrderSecondFacebook(): ReportCustomerOrderSale
    {
        $total_customer_order_second_facebook_ads = $this->result->sum('total_customer_order_second_fb_ads') ?? 0;
        $this->total_customer_order_second_facebook_ads = $total_customer_order_second_facebook_ads;
        return $this;
    }

    /*Giá trị đơn thứ 2 trở đi*/
    public function amountSecond(): ReportCustomerOrderSale
    {
        $total_amount_second = $this->result->sum('total_amount_second') ?? 0;
        $this->total_amount_second = $total_amount_second;
        return $this;
    }

    /*Phí dịch vụ đơn thứ 2 trở đi*/
    public function serviceChargeSecond(): ReportCustomerOrderSale
    {
        $total_service_charge_second = $this->result->sum('total_service_charge_second') ?? 0;
        $this->total_service_charge_second = $total_service_charge_second;
        return $this;
    }

    public function exec(): \stdClass
    {
        $results = new \stdClass();
        $results->total_customer_consignment_advise = $this->total_customer_consignment_advise;
        $results->total_customer_order_advise = $this->total_customer_order_advise;
        $results->total_customer_consignment = $this->total_customer_consignment;
        $results->total_customer_order_first = $this->total_customer_order_first;
        $results->total_customer_order_first_google_ads_personal = $this->total_customer_order_first_google_ads_personal;
        $results->total_customer_order_first_google_ads_business = $this->total_customer_order_first_google_ads_business;
        $results->total_customer_order_first_facebook_ads_personal = $this->total_customer_order_first_facebook_ads_personal;
        $results->total_customer_order_first_facebook_ads_business = $this->total_customer_order_first_facebook_ads_business;
        $results->total_amount_first = $this->total_amount_first;
        $results->total_amount_first_google_ads_personal = $this->total_amount_first_google_ads_personal;
        $results->total_amount_first_google_ads_business = $this->total_amount_first_google_ads_business;
        $results->total_amount_first_facebook_ads_personal = $this->total_amount_first_facebook_ads_personal;
        $results->total_amount_first_facebook_ads_business = $this->total_amount_first_facebook_ads_business;
        $results->total_service_charge_first = $this->total_service_charge_first;
        $results->total_service_charge_first_google_ads_personal = $this->total_service_charge_first_google_ads_personal;
        $results->total_service_charge_first_google_ads_business = $this->total_service_charge_first_google_ads_business;
        $results->total_service_charge_first_facebook_ads_personal = $this->total_service_charge_first_facebook_ads_personal;
        $results->total_service_charge_first_facebook_ads_business = $this->total_service_charge_first_facebook_ads_business;
        $results->total_customer_order_second_google_ads = $this->total_customer_order_second_google_ads;
        $results->total_customer_order_second_facebook_ads = $this->total_customer_order_second_facebook_ads;
        $results->total_amount_second = $this->total_amount_second;
        $results->total_service_charge_second = $this->total_service_charge_second;

        return $results;
    }
}
