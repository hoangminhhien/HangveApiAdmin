<?php

namespace App\Http\Resources\Package;

use App\Constants\PackageConstant;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\Resource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ReportOrderVN;
use App\Models\ReportCustomer;
use App\Models\OrderPackage;
use App\Models\ReportPackage;
use App\Services\OrderPackageService;
use App\Services\ReportCustomerService;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TimeHelper;
use App\Constants\TimeConstant;
use Illuminate\Support\Facades\DB;

class PackagePaginationResource extends PaginateJsonResource
{
    public function __construct($resource, ?string $class = Resource::class)
    {
        parent::__construct($resource, $class);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        $data = parent::toArray($request);

//        list($debt_cost)   = $this->getDebtCost();
//        $data['debt_cost'] = $debt_cost;
//
//        $data['reports'] = [];
//        $data['status_warehouse_vn']['quantity'] = 0;
//
//        $customer = $this->getCustomer();
//        if (!$customer) {
//            return $data;
//        }
//
//        $data['status_warehouse_vn']['quantity'] = $customer->packagesVN()->withoutGlobalScopes()->count();
//
//        if (request()->query('status') !== PackageConstant::STATUS_WAREHOUSE_VN) {
//            return $data;
//        }
//
//        $data['reports'] = $this->getCalculator($customer);

        return $data;
    }

    public function getDebtCost()
    {
        $query = OrderPackage::query();

        if (request()->query('customer_code') || request()->query('customer_name') || request()->query('customer_phone')) {
            $query->join('customers', 'customers.id', '=', 'order_package.customer_id');
        }

        $costByStatus = $query
            ->selectRaw(
                DB::raw(
                    'SUM(ROUND( ROUND( COALESCE(international_shipping_cost,0) +
                    (COALESCE(china_shipping_cost, 0) * exchange_rate ) + COALESCE(insurance_cost, 0) +
                    COALESCE(inspection_cost, 0) + COALESCE(woodworking_cost, 0) + COALESCE(shock_proof_cost, 0)
		            + COALESCE(storage_cost, 0) + COALESCE(delivery_cost, 0) ) - COALESCE(discount_cost, 0) )
	                ) as totalAmount'
                )
            )
            ->whereNotIn('order_package.status', [PackageConstant::STATUS_RECEIVED_GOODS, PackageConstant::STATUS_CANCEL])
            ->groupBy('order_package.status')
            ->get();

        $debt_cost = 0;
        foreach ($costByStatus as $item) {
            $debt_cost += floatval($item->totalAmount);
        }

        return [$debt_cost];
    }

    private function getCustomer()
    {
        if (!request()->query('customer_code') && !request()->query('customer_name') &&
            !request()->query('customer_phone')) {
            return null;
        }

        $query = Customer::query()->withoutGlobalScopes()->with([
//            'packagesVN' => function($q) {
//               return $q->select('id');
//            },
            'reportOrderVN',
            'report' => function($q) {
                return $q->select('balance_amount');
            }
        ]);

        $query->select('id');

        if(!!request()->query('customer_code'))
        {
            $query->where('code', request()->query('customer_code'));
        }
        if (!!request()->query('customer_name'))
        {
            $query->where('name', 'like', '%' . request()->query('customer_name') .'%');
        }
        if (!!request()->query('customer_phone'))
        {
            $query->where('phone_number', request()->query('customer_phone'));
        }

        return $query->first();
    }

    private function getCalculator(Customer $customer): array
    {
        $data = [];

        //$reports = ReportOrderVN::query()->where(['customer_id' => $customer->id])->get();
        $reports = $customer->reportOrderVN;

        $depositCost = 0;
        $orderCost = 0;
        $shipping = 0;
        $data['orders'] = [];

        foreach ($reports as $report) {
            if($report->order_amount != 0)
            {

                $data['orders'][] = [
                    'time' => $report->date_ordered,
                    'code' => $report->order_code,
                    'amount' => $report->order_amount,
                    'deposit_cost' => $report->deposit_cost,
                    'debt_cost' => $report->order_amount - $report->deposit_cost
                ];
            }
            // Hàng liên quan đế ký gửi thì không có tiền hàng và tiền tạm ứng
            //if($report->order instanceof Order) {
            if($report->order_type === Order::class) {
                $depositCost += $report->deposit_cost;
                $orderCost += $report->order_amount;
            }
            $shipping += $report->shipping_cost;
        }

        $data['orders_remainder_fee'] = $orderCost - $depositCost;
        $data['total_shipping'] = $shipping;
//        $data['e_wallet'] = $eWallet = optional(
//            (new ReportCustomerService())->getReportCustomerCurrent($customerId)
//        )->balance_amount ?? 0;
        $data['e_wallet'] = $eWallet = optional($customer->report)->balance_amount ?? 0;
        //ReportCustomer::query()->sum('balance_amount'); // ???
        $balance = $eWallet - $data['orders_remainder_fee'] - $shipping;
        $data['balance'] = $balance > 0 ? $balance : 0;
        $data['recharge'] = $balance > 0 ? 0 : abs($balance);

        return $data;
    }
}
