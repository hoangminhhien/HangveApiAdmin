<?php


namespace App\Services;

use App\Helpers\PaginateHelper;
use Illuminate\Http\Request;
use App\Models\FundTransaction;
use App\Http\Resources\FundTransaction\FundTransactionResource;
use App\Http\Resources\FundTransaction\FundTransactionListResource;
use App\Http\Resources\FundTransaction\FundTransactionPaginateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FundTransactionService extends BaseService
{
    protected string $_paginateResource = FundTransactionPaginateResource::class;
    protected string $_listResource = FundTransactionListResource::class;
    protected string $_resource = FundTransactionResource::class;


    // Bên VN cần check quyền nên tách từ hàm pagination mặc định ra
    public function paginationVN(int $perPage, $params = [])
    {
        $this->throwModel();

        $query = $this->_model->newQuery();
        $query->with('user', 'fund_type_pay', 'customer');

        // Nếu không được quyền xem tất cả thì lọc
        $userOnline = Auth::user();
        $viewAll = $userOnline->checkUserHasPer('fund.ls-vn-view-all');
        if (!$viewAll) {
            $query = $query->where('user_create_id', (int) $userOnline->id);
        }
        if (!empty($params['is_total'])) {
            return $this->calculatorTotal($query);
        }
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    private function calculatorTotal($query): array
    {
        $total_record = 0;
        $total_revenue = 0;
        $total_cost = 0;
        foreach ($query->cursor() as $cursor) {
            $total_record ++;
            if ($cursor->type_pay == 1) {
                $total_revenue += abs($cursor->money_update);
            }
            if ($cursor->type_pay == 2) {
                $total_cost += abs($cursor->money_update);
            }
        }
        $total_difference = $total_revenue - $total_cost;
        return [
            'total_record' => $total_record,
            'total_record_format' => number_format($total_record),
            'total_revenue' => $total_revenue,
            'total_revenue_format' => number_format($total_revenue),
            'total_cost' => $total_cost,
            'total_cost_format' => number_format($total_cost),
            'total_difference' => $total_difference,
            'total_difference_format' => number_format($total_difference),
        ];
    }

    public function getTotalFund($params)
    {
        return $this->paginationVN(PaginateHelper::getPerPage(), $params);
    }


    /**
     * @param string $orderId
     * @return int
     */

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();

        //$perService = \App::make('App\Services\PerService');

        $query = $this->_model->newQuery();
        $query->with('user', 'fund_type_pay', 'customer');

        //$query = $perService->getConditionByUserLogin($query);
        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    public function paginationDelete(int $perPage): JsonResponse
    {
        $this->throwModel();

        $query = $this->_model->newQuery()
            ->onlyTrashed()
            ->where('fund_unit_currency', 1)
            ->orderBy('updated_at', 'desc');
        $query->with('user', 'fund_type_pay', 'customer');

        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    public function paginationDeleteTQ(int $perPage): JsonResponse
    {
        $this->throwModel();

        $query = $this->_model->newQuery()
            ->onlyTrashed()
            ->where('fund_unit_currency', 2)
            ->orderBy('updated_at', 'desc');
        $query->with('user', 'fund_type_pay', 'customer');

        $data = $query->paginate($perPage);

        return resSuccessWithinData(new $this->_paginateResource($data));
    }

    public function export()
    {
        $this->throwModel();
        $query = $this->_model->newQuery()->with('user', 'fund_type_pay', 'customer');
        $data = $query->orderBy('created_at', 'desc')->get();
        return $data;
    }

    public function store(array $data)
    {
        $this->throwModel();
        $userOnline = \Auth::user();

        $fundModel = \App::make('App\Models\Fund');
        $fund = $fundModel->where('id', $data['fund_id'])->withoutGlobalScopes()->first();

        // Lưu thêm thông tin
        $data['fund_name'] = $fund->name;
        $data['fund_type_currency'] = $fund->type_currency;
        $data['fund_unit_currency'] = $fund->unit_currency;

        if ($data['type_object'] == 2) {
            $customerModel = \App::make('App\Models\Customer');
            if ($data['customer_code']) {
                $customer = $customerModel->where('code', $data['customer_code'])->first();
                if ($customer) {
                    $data['customer_name'] = $customer->name;
                    $data['customer_phone'] = $customer->phone_number;
                    $data['customer_id'] = (int) $customer->id;
                }
            }
        }
        if (!isset($data['organization_id'])) {
            $data['organization_id'] = $userOnline->organization_id;
        }
        $data['user_create_id'] = $userOnline->id;
        $data['status'] = 1;
        $data['code'] = $this->generateRandomCode();
        $data['money_update'] = $data['money'];

        // Thêm log
        $temp_log = [
            'action'             => 'Tạo mới',

            'user_request_name'  => $userOnline->name,
            'user_request_email' => $userOnline->email,
            'user_request_id'    => $userOnline->id,

            'column'             => "",
            'value_old'          => "",
            'value_new'          => "",

            'time'               => date('Y-m-d H:i:s', time())
        ];

        $data['logs'] = json_encode([$temp_log], JSON_UNESCAPED_UNICODE);
        // Cập nhật tiền quỹ tồn
        if($fund){
            // giao dich thu
            if(data_get($data, 'type_pay') == FundTransaction::REVENUE_TYPE_PAY){
                $fund->increment('total_money_in', data_get($data, 'money', 0));
                $fund->increment('current_balance', data_get($data, 'money', 0));
            }
            // giao dich chi
            if(data_get($data, 'type_pay') == FundTransaction::EXPENSE_TYPE_PAY){
                $fund->increment('total_money_out', data_get($data, 'money', 0));
                $fund->decrement('current_balance', data_get($data, 'money', 0));
            }
        }
        $data['balance'] = data_get($fund, 'current_balance');
        // Tạo bản ghi
        $result = $this->_model->newQuery()->create($data);
        return $result;
    }

    public function generateRandomCode() {
        $min = 100000;
        $max = 999999;
        $rand = rand($min,$max);
        return 'FD_' . date('Ymd') . '_' . $rand;
    }
}
