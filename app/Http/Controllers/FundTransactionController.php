<?php

namespace App\Http\Controllers;

use App\Models\ReportCustomer;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\FundTransactionService;
use App\Models\FundTransaction;
use App\Models\Fund;
use App\Models\LogFundTransaction;
use Carbon\Carbon;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Constants\OrderConstant;
use App\Models\Order;
use App\Exports\FundTransactionExport;
use Maatwebsite\Excel\Facades\Excel;

use App\Helpers\PaginateHelper;

class FundTransactionController extends Controller
{
    public function __construct(FundTransactionService $service)
    {
        $this->_service = $service;
    }

    public function paginationVN(): JsonResponse
    {
        return $this->_service->paginationVN(PaginateHelper::getPerPage());
    }

    public function getTotalFund(Request $request): JsonResponse
    {
        $conditions = $request->all();
        $key_cache = $this->makeKeyCache($conditions);
        $dataCache = $this->getCacheTotalFund($key_cache);
        return resSuccessWithinData($dataCache);
    }

    private function getCacheTotalFund($key_cache){
        $dataCache = Cache::get($key_cache, []);
        $params['is_total'] = true;
        if (empty($dataCache)) {
            $data = $this->_service->getTotalFund($params);
            Cache::put($key_cache, $data, now()->addMinutes(10));
            $dataCache = $data;
        }
        return $dataCache;
    }

    private function makeKeyCache($conditions): string
    {
        $key_cache = Auth::user()->id .'_';
        if (isset($conditions['page'])) {
            unset($conditions['page']);
        }
        if (isset($conditions['per_page'])) {
            unset($conditions['per_page']);
        }
        if (isset($conditions['id_sort'])) {
            unset($conditions['id_sort']);
        }
        foreach ($conditions as $key => $condition) {
            if (!empty($condition)) {
                $key_cache .= $key.'_'.$condition.'_';
            }
        }
        return $key_cache;
    }

    public function refreshCacheWithCondition(Request $request): JsonResponse
    {
        $conditions = $request->all();
        $key_cache = $this->makeKeyCache($conditions);
        Cache::forget($key_cache);
        $dataCache = $this->getCacheTotalFund($key_cache);
        return resSuccessWithinData($dataCache);
    }

    public function getCategoryVi(Request $request): JsonResponse
    {
        $fundModel = App::make('App\Models\Fund');
        $funds = $fundModel->where('unit_currency', Fund::VND_UNIT_CURRENCY)->get();

        // Thêm theo quyền
        $userOnline = \Auth::user();
        $hasPerrReserveAll = $userOnline->checkUserHasPer('fund.view-reserve-all');
        $hasPerrReserveWarehouse = $userOnline->checkUserHasPer('fund.view-reserve-warehouse');

        // Nếu không dc tích 1 trong 2 quyền xem thì funds = [];
        if ($hasPerrReserveAll == false && $hasPerrReserveWarehouse == false) {
            $funds = [];
        }
        // Nếu chỉ có quyền kho
        if ($hasPerrReserveWarehouse) {
            $funds = $fundModel->where('type_common', Fund::TYPE_COMMON_WAREHOUSE)->where('unit_currency', Fund::VND_UNIT_CURRENCY)->get();
            // Logic 04-12-24: Logic lọc theo kho làm việc của User
            $departmentDB = $userOnline->department;
            if (!is_null($departmentDB) && $departmentDB->warehouse_id > 0) {
                $funds = $funds->where('warehouse_id', $departmentDB->warehouse_id);
            }
        }

        // Nếu chỉ có quyền xem tất cả
        if ($hasPerrReserveAll) {
            $funds = $fundModel->where('unit_currency', Fund::VND_UNIT_CURRENCY)->get();
        }


        $arrFund = [];
        $arrFundCash = [];
        $arrFundBank = [];
        if ($funds) {
            foreach ($funds as $key => $fund) {
                $temp = [
                    'value' => (int)$fund->id,
                    'name' => $fund->name,
                    'current_balance' => $fund->current_balance
                ];
                if ($fund->type_currency == 1) {
                    $arrFundCash[] = $temp;
                }
                if ($fund->type_currency == 2) {
                    $arrFundBank[] = $temp;
                }
                $arrFund[] = $temp;
            }
        }

        $arrTypeObject = [
            [ 'value' => 1, 'name' => 'Chuyển quỹ' ],
            [ 'value' => 2, 'name' => 'Khách hàng' ],
            [ 'value' => 3, 'name' => 'Nội bộ' ],
        ];

        $arrTypeObjectInForm = [
            [ 'value' => 2, 'name' => 'Khách hàng' ],
            [ 'value' => 3, 'name' => 'Nội bộ' ],
        ];

        $arrTypePay = [
            [ 'value' => 1, 'name' => 'Khoản thu' ],
            [ 'value' => 2, 'name' => 'Khoản chi' ],
        ];

        $arrTypePay2 = [
            [ 'value' => 1, 'name' => 'Phiếu thu' ],
            [ 'value' => 2, 'name' => 'Phiếu chi' ],
        ];

        $arrTypeCurrency = [
            [ 'value' => 1, 'name' => 'Sổ quỹ tiền mặt' ],
            [ 'value' => 2, 'name' => 'Sổ quỹ ngân hàng' ],
        ];

        $fundTypePayModel = App::make('App\Models\FundTypePay');

        $arrTypePayValue1 = [];
        $typePayment1 = $fundTypePayModel->where('status', 1)
                                            ->where('type', 1)
                                            ->get();
        if ($typePayment1) {
            foreach ($typePayment1 as $key => $item) {
                $temp = [
                    'value' => (int)$item->id,
                    'name' => $item->name,
                    'code' => $item->code
                ];
                $arrTypePayValue1[] = $temp;
            }
        }

        $arrTypePayValue2 = [];
        $typePayment2 = $fundTypePayModel->where('status', 1)
                                            ->where('type', 2)
                                            ->get();
        if ($typePayment2) {
            foreach ($typePayment2 as $key => $item) {
                $temp = [
                    'value' => (int)$item->id,
                    'name' => $item->name
                ];
                $arrTypePayValue2[] = $temp;
            }
        }


        $arrFundAll = [];
        $arrFundUnit = []; // Dùng để check xem nó là tiền nước nào để lấy mảng tiền tương ứng
        $arrFundAllVN = [];
        $arrFundAllChina = [];
        $arrFundAllVirtualTe = []; // Quỹ ảo tiền tệ

        $fundAlls = $fundModel->where('status', 1)->get();

        // Nếu không dc tích 1 trong 2 quyền xem thì funds = [];
        if ($hasPerrReserveAll == false && $hasPerrReserveWarehouse == false) {
            $fundAlls = [];
        }
        // Nếu chỉ có quyền kho
        if ($hasPerrReserveWarehouse) {
            $fundAlls = $fundModel->where('type_common', Fund::TYPE_COMMON_WAREHOUSE)->where('status', 1)->get();
            // Logic 04-12-24: Logic lọc theo kho làm việc của User
            $departmentDB = $userOnline->department;
            if (!is_null($departmentDB) && $departmentDB->warehouse_id > 0) {
                $fundAlls = $fundAlls->where('warehouse_id', $departmentDB->warehouse_id);
            }
        }

        // Nếu chỉ có quyền xem tất cả
        if ($hasPerrReserveAll) {
            $fundAlls = $fundModel->where('status', 1)->get();
        }


        if ($fundAlls) {
            foreach ($fundAlls as $key => $fund) {
                $temp = [
                    'value' => (int)$fund->id,
                    'name' => $fund->name,
                ];
                $temp_unit= [
                    'value' => (int)$fund->id,
                    'unit_currency' => $fund->unit_currency,
                ];
                if (
                    $fund->unit_currency == Fund::VND_UNIT_CURRENCY
                    && $fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY
                ) {
                    array_push($arrFundAllVN, $temp);
                }
                if (
                    $fund->unit_currency == Fund::CHINESE_UNIT_CURRENCY
                    && $fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY
                ) {
                    array_push($arrFundAllChina, $temp);
                }

                if (
                    $fund->unit_currency == Fund::CHINESE_UNIT_CURRENCY
                    && $fund->type_currency == Fund::VIRTUAL_TYPE_CURRENCY
                ) {
                    array_push($arrFundAllVirtualTe, $temp);
                }
                array_push($arrFundAll, $temp);
                array_push($arrFundUnit, $temp_unit);
            }
        }

        $arrFundAllGroup = [
            [
                'label' => '----- Tiền Việt Nam -----',
                'options' => $arrFundAllVN,
            ],
            [
                'label' => '----- Tiền Trung Quốc -----',
                'options' => $arrFundAllChina,
            ]
        ];

        $arrFundAllChinaAndVirtual = [
            [
                'label' => '----- Tiền Trung Quốc -----',
                'options' => $arrFundAllChina,
            ],
            [
                'label' => '----- Tiền ảo -----',
                'options' => $arrFundAllVirtualTe,
            ]
        ];


        // Làm thêm ngày 3-12-2024: mục chuyển quỹ phần đến quỹ hiển thị full
        $fundAllsNotPer = $fundModel->where('status', 1)->get();
        $arrFundAllVNNotPer = [];
        $arrFundAllChinaNotPer = [];
        if ($fundAllsNotPer) {
            foreach ($fundAllsNotPer as $key_not_per => $fund) {
                $temp = [
                    'value' => (int)$fund->id,
                    'name' => $fund->name,
                ];
                if (
                    $fund->unit_currency == Fund::VND_UNIT_CURRENCY
                    && $fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY
                ) {
                    array_push($arrFundAllVNNotPer, $temp);
                }
                if (
                    $fund->unit_currency == Fund::CHINESE_UNIT_CURRENCY
                    && $fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY
                ) {
                    array_push($arrFundAllChinaNotPer, $temp);
                }
            }
        }

        $arrFundAllGroupNotPer = [
            [
                'label' => '----- Tiền Việt Nam -----',
                'options' => $arrFundAllVNNotPer,
            ],
            [
                'label' => '----- Tiền Trung Quốc -----',
                'options' => $arrFundAllChinaNotPer,
            ]
        ];


        $data_result = [
            'arrTypeObject'       => $arrTypeObject,
            'arrTypeObjectInForm' => $arrTypeObjectInForm,

            'arrTypeCurrency'     => $arrTypeCurrency,

            'arrTypePay'          => $arrTypePay,
            'arrTypePay2'         => $arrTypePay2,
            'arrTypePayValue1'    => $arrTypePayValue1,
            'arrTypePayValue2'    => $arrTypePayValue2,

            'arrFund'             => $arrFund,
            'arrFundCash'         => $arrFundCash,
            'arrFundBank'         => $arrFundBank,

            // Màn hình chuyển quỹ sử dụng các key phía dưới
            'arrFundAll'          => $arrFundAll,
            'arrFundUnit'         => $arrFundUnit,
            'arrFundAllVN'        => $arrFundAllVN,
            'arrFundAllChina'     => $arrFundAllChina,
            'arrFundAllGroup'     => $arrFundAllGroup,
            'arrFundAllChinaAndVirtual'     => $arrFundAllChinaAndVirtual,

            'arrFundAllGroupNotPer' => $arrFundAllGroupNotPer, // Không cần check Per
        ];

        return resSuccessWithinData($data_result);
    }

    public function getCategoryCn(Request $request)
    {
        $fundModel = \App::make('App\Models\Fund');
        $funds = $fundModel->where('unit_currency', Fund::CHINESE_UNIT_CURRENCY)->get();
        $arrFundCN = [];
        if ($funds) {
            foreach ($funds as $key => $fund) {
                $temp2 = [
                    'id' => (int)$fund->id,
                    'name' => $fund->name,
                ];
                array_push($arrFundCN, $temp2);
            }
        }


        $arrTypePay = [
            [ 'value' => 1, 'name' => 'Khoản thu' ],
            [ 'value' => 2, 'name' => 'Khoản chi' ],
        ];

        $data_result = [
            'arrFundCN'  => $arrFundCN,
            'arrTypePay' => $arrTypePay,
        ];

        return resSuccessWithinData($data_result);
    }

    public function storeMessage(): ?array
    {
       return [
        'customer_id.required_if' => 'Khách hàng không được để trống',
        'note.max'                 => 'Ghi chú không được quá 1000 kí tự'
       ];
    }

    public function storeRequest(): array
    {
        return [
            'type_object'      => 'required|in:2,3',
            'type_pay'         => 'required|in:1,2',
            'fund_type_pay_id' => 'required|exists:fund_type_pays,id',
            'fund_id'          => 'required|exists:funds,id',
            'customer_code'    => 'required_if:type_object,==,2',
            'money'            => 'required|numeric|min:0|max:10000000000',
            'note'             => 'required|string|max:1000',
        ];
    }

    public function store(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        $data = request()->all();
        $data = $this->handleBeforeStore($data);

        if(!is_array($data)) {
            return $data;
        }

        // Đảm bảo không có lệnh nạp tiền từ app thực thi đồng thời với lệnh này
        $job = new \App\Jobs\Deposit\ChargeAdmin();
        dispatch($job)->delay(now()->addSeconds(1))->onQueue('cron-data-2023');

        try {
            \DB::beginTransaction();
            $customer = $data['customer'];
            $result = $this->_service->store($data);

            if(data_get($result, 'type_object') == FundTransaction::CUSTOMER_TYPE_OBJECT){
                // Giao dich thu
                if(data_get($result, 'type_pay') == FundTransaction::REVENUE_TYPE_PAY){
                    $amount = (int) $result->money;
                    $content = $data['fund_type_name'];
                    $content = $data['note']; // Logic ngày 11/10/2024
                    $customer_id = $customer->id;
                    $soure = $result;

                    (new \App\Services\TransactionService())->setTransactionIncrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                        $content,
                        $customer_id,
                        $soure
                    );
                    $this->updateNotiLogic($customer, $data);
                    // Cộng vào số tiền khách đã nạp
                    ReportCustomer::query()->where('customer_id', $customer_id)->increment('deposited_amount', $amount);
                }
                // Giao dich chi
                if(data_get($result, 'type_pay') == FundTransaction::EXPENSE_TYPE_PAY){
                    // Trừ tiền của khách
                    $amount = (int) $result->money;
                    $content = $data['fund_type_name'];
                    $content = $data['note']; // Logic ngày 11/10/2024
                    $customer_id = $customer->id;
                    $soure = $result;

                    // (new \App\Services\TransactionService())->setTransactionDecrement(
                    //     $amount,
                    //     \App\Constants\TransactionConstant::STATUS_PURCHASE,
                    //     $content,
                    //     $customer_id,
                    //     $soure
                    // );

                    // Cho phép trừ âm luôn (Trường hợp nào ko dc trừ âm thì đã chặn ở Validate rồi)
                    $isIncrement = false;
                    $isBalanceNegative = true;
                    $order = null;

                    (new \App\Services\TransactionService())->setTransaction(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_PURCHASE,
                        $content,
                        $isIncrement,
                        $customer_id,
                        $soure,
                        $isBalanceNegative,
                        $order
                    );

                    ReportCustomer::query()->where('customer_id', $customer_id)->increment('purchase_amount', $amount);
                    // Cộng vào số tiền khách rút
                    // ReportCustomer::query()->where('customer_id', $customer_id)->increment('withdrawal_amount', $amount);
                }
            }

            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    private function handleBeforeStore($data)
    {
        $data['customer'] = '';
        // Nếu không phải đối tượng là khách hàng thì bỏ customer_code đi
        if ($data['type_object'] != FundTransaction::CUSTOMER_TYPE_OBJECT) {
            unset($data['customer_code']);
            unset($data['customer_name']);
            unset($data['customer_phone']);

            // Không cho nó chọn cái fund_type_pay_id có code bằng 1
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypePayCode1 = $fundTypePayModel->where('code', 1)->first();
            if ($data['fund_type_pay_id'] == $fundTypePayCode1->id) {
                $result = [
                    'money' => 'Không được phép chọn khách hàng nạp tiền với loại đối tượng không phải là khách hàng'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        };

        if ($data['type_object'] == FundTransaction::CUSTOMER_TYPE_OBJECT) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $data['customer_code'])->first();
            $data['customer'] = $customer;
            if (is_null($customer)) {
                $result = [
                    'customer_code' => 'Khách hàng không tồn tại trên hệ thống'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            // Gán thêm mã công ty theo khách hàng
            $data['organization_id'] = $customer->organization_id;
        }

        // Thêm logic nếu là NH cho vay (thuongdo_id = 16) thì không check số dư ví, cho trừ âm ví khách
        $fundModel = \App::make('App\Models\Fund');
        $isNHChoVay = false;
        $fundDB = $fundModel->where('id', $data['fund_id'])->first();
        if (!is_null($fundDB)) {
            if ($fundDB->name == 'NH cho vay' && $fundDB->thuongdo_id == 16) {
                $isNHChoVay = true;
            }
        }

        // Kiểm tra nếu là trừ tiền thì phải có đủ tiền
        if ($data['type_pay'] == FundTransaction::EXPENSE_TYPE_PAY && $isNHChoVay == false) {

            $fundModel = \App::make('App\Models\Fund');
            $fund = $fundModel->where('id', $data['fund_id'])->withoutGlobalScopes()->first();
            /** type_currency = 3 thì là tiền ảo - Tiền ảo có thể âm nên k cần check */
            if ($fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY && $fund->current_balance < $data['money']) {
                $result = [
                    'money' => 'Quỹ không đủ số dư, không thể tạo phiếu chi'
                ];
                // return resErrorWithinData($result, 'Có lỗi xảy ra', 422); // Logic 12/09/2024 cho phép trừ âm quỹ luôn
            }
            // Nếu chi tiền cho khách thì khách cũng phải có đủ tiền để trừ
            /** Tức là nếu khách nạp 3tr thì tài khoản của khách có 3tr.
             * Ví của mình cũng có tiền.
             * Mình chi cho khách 50k thì cũng phải trừ vào ví của khách 50k */
            if($fund->type_currency != Fund::VIRTUAL_TYPE_CURRENCY && $data['type_object'] == FundTransaction::CUSTOMER_TYPE_OBJECT) {
                $customerModel = \App::make('App\Models\Customer');
                $customer = $customerModel->where('code', $data['customer_code'])->first();

                $customerService = \App::make('App\Services\CustomerService');;
                $customerBalance = (int)$customerService->getBalanceAmount($customer->id);

                if ($customerBalance < $data['money']) {
                    $result = [
                        'money' => 'Số dư ví của khách không đủ tạo lệnh chi tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
            }
        }

        // Kiểm tra fund_type_pay_id phải khớp với loại thu chi
        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $fundTypePayDB = $fundTypePayModel->where('type', $data['type_pay'])
                                        ->where('id', $data['fund_type_pay_id'])
                                        ->first();
        if (is_null($fundTypePayDB)) {
            $result = [
                'fund_type_pay_id' => 'Loại thu chi không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        $data['fund_type_pay_code'] = $fundTypePayDB->code;
        $data['fund_type_name'] = $fundTypePayDB->name;

        return $data;
    }

    // nạp tiền cho khách thì lưu noti
    private function updateNotiLogic($customer, $data, $contentNoti = null)
    {
        $typeCode = $data['fund_type_pay_code'];

        if(isset($data['note']) && $typeCode == 9) {
            $data_customer = [
                'content'  => $data['note']
            ];
        } else {
            $data_customer = [
                'content' => $contentNoti ?? 'Chuyển tiền mua hàng thành công'
            ];
        }
        $notificationService = \App::make('App\Services\NotificationService');

        $data = [
            'group'           => 8,
            'name'            => 8001,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customer->id,
            'status'          => 1
        ];

//        $notificationService->store($data);
        $notificationService->storeNotificationCustomer($customer->id, $data);


        // push notification app
        $dataNoti = $this->getDataNotiByFundType($typeCode);
        (new FcmService())->sendNotifyUser([$customer->id], $dataNoti);
    }

    public function getDataNotiByFundType($typeCode)
    {
        $title = 'Thông báo';
        $body  = '';
        $data  = [
            'screen' => 'TransactionList',
        ];

        switch ($typeCode) {
            case 1: // khach nap tien
                $body  = 'Nạp tiền thành công';
                break;
            case 5: // khach rut tien
                $body  = 'Rút tiền thành công';
                break;
            case 9: // hoan tien
                $body  = 'Hoàn tiền thành công';
                break;
        }

        return [
            'title' => $title,
            'body'  => $body,
            'data'  => $data
        ];
    }

    public function store30012023(): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__);
        $data = request()->all();
        // Nếu không phải đối tượng là khách hàng thì bỏ customer_code đi
        if ($data['type_object'] != 2) {
            unset($data['customer_code']);
        };

        if ($data['type_object'] == 2) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $data['customer_code'])->first();

            if (is_null($customer)) {
                $result = [
                    'customer_code' => 'Khách hàng không tồn tại trên hệ thống'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        // Kiểm tra nếu là trừ tiền thì phải có đủ tiền
        if ($data['type_pay'] == 2) {
            $fundModel = \App::make('App\Models\Fund');
            $fund = $fundModel->where('id', $data['fund_id'])->withoutGlobalScopes()->first();
            if ($fund->current_balance < $data['money']) {
                $result = [
                    'money' => 'Quỹ không đủ số dư, không thể tạo phiếu chi'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        try {
            \DB::beginTransaction();
            $result = $this->_service->store($data);
            \DB::commit();
            return resSuccessWithinData($result);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function getCustomer()
    {
        $data = request()->all();

        if (is_null($data['customer_search'])) {
            $result = [
                'customer_has' => 0,
                'customer_code' => "",
                'customer_phone' => "",
                'customer_name' => "",
            ];
            return resSuccessWithinData($result);
        }

        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_search'])
                                ->orWhere('phone_number', $data['customer_search'])
                                ->where('status', 1)
                                ->first();

        if ($customer) {
            $result = [
                'customer_has' => 1,
                'customer_code' => $customer->code,
                'customer_phone' => $customer->phone_number,
                'customer_name' => $customer->name,
            ];
            return resSuccessWithinData($result);
        }

        $result = [
            'customer_has' => 0,
            'customer_code' => "",
            'customer_phone' => "",
            'customer_name' => "",
        ];
        return resSuccessWithinData($result);
    }

    public function hide(string $id)
    {
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransaction = $fundTransactionModel->where('id', $id)->where('status', 1)->first();
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $fundTransaction->customer_code)->first();

        if (is_null($fundTransaction)) {
            return resError('Bản ghi không tồn tại');
        }

        // Kiểm tra sau 1 tháng thì không cho xử lý xóa nữa
        $timeCreatedAt = Carbon::parse($fundTransaction->created_at)->addMonths(5)->timestamp;
        if (Carbon::now()->timestamp > $timeCreatedAt) {
            return resError('Quá thời gian cho phép chỉnh sửa');
        }

        try {
            \DB::beginTransaction();
            // Trừ tiền của quỹ
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypeHoanTien = $fundTypePayModel->where('code', 6)->first();
                if (is_null($fundTypeHoanTien)) {
                $result = [
                    'unit' => 'Loại thanh toán hoàn tiền chưa được khởi tạo, liên hệ admin'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            // Nếu loại là nạp tiền cho khách hàng
            $type_object        = $fundTransaction->type_object; // Loại là khách hàng
            $type_pay           = $fundTransaction->type_pay; // Hình thức là thu tiền
            $fund_type_pay_code = $fundTransaction->fund_type_pay_code; // Loại là thu tiền khách hàng

            if ($type_object == 2 && $type_pay == 1 && $fund_type_pay_code == 1) {
                if ($fundTransaction->customer_code == null) {
                    $result = [
                        'money' => 'Không thể xóa vì chưa xác định được khách hàng'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                $customerModel = \App::make('App\Models\Customer');
                $customer = $customerModel->where('code', $fundTransaction->customer_code)->first();

                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();

                // Xóa giao dịch thì - đi số tiên giao dịch tương ứng ở tồn quỹ
                $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                $fundDB->decrement('current_balance', $fundTransaction->money_update);
                // Tạo 1 phiếu trừ tiền của khách
                $amount = (int) $fundTransaction->money_update;
                $content = 'Hủy nạp tiền vào ví điện tử';
                $customer_id = $customer->id;
                $soure = $fundTransaction;

                (new \App\Services\TransactionService())->setTransactionDecrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                    $content,
                    $customer_id,
                    $soure
                );
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
                ReportCustomer::query()->where('customer_id', $customer_id)->decrement('deposited_amount', $amount);
                $this->storeNotiWhenHide($customer, $content);
            }

            // Nếu là chuyễn quỹ
            if ($type_object == 1 && $fund_type_pay_code == 3) {
                // Tìm cái bản ghi liên quan
                $fundTransactionRelation = $fundTransactionModel->where('id', $fundTransaction->fund_transaction_id)->first();
                if ($fundTransactionRelation) {

                    // Check số dư
                    $fund_id = $fundTransaction->fund_id;
                    if ($fundTransactionRelation->type_pay == 1) {
                        $fund_id = $fundTransactionRelation->fund_id;
                    }

                    $fundModel = \App::make('App\Models\Fund');
                    $fundDB = $fundModel->where('id', $fund_id)->first();

                    if ($fundDB && $fundTransaction->money_update > $fundDB->current_balance) {
                        $result = [
                            'unit' => 'Số dư quỹ không đủ để xóa nạp tiền'
                        ];
                        return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                    }

                    $this->rollbackBalance($fundTransactionRelation, $fundTypeHoanTien);
                }
            }

            // Nếu là xóa nạp tiền TQ
            if ($type_object == 3 && $fund_type_pay_code == 4) {
                // Kiểm tra xem số dư của quỹ có đủ không á

                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();

                if ($fundDB && $fundTransaction->money_update > $fundDB->current_balance) {
                    $result = [
                        'unit' => 'Số dư quỹ TQ không đủ để xóa nạp tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Nếu là giao dịch nhận tiền của Trung thì tìm toàn bộ các bản ghi của Việt để ẩn
                if ($type_pay == 1) {
                    $fundTransactionRelations = $fundTransactionModel->where('fund_transaction_id', $id)->get();

                    if (count($fundTransactionRelations)) {
                        foreach ($fundTransactionRelations as $key => $item) {
                            $this->rollbackBalance($item, $fundTypeHoanTien);
                        }
                    }
                }

                // Nếu là chi ra thì phải tìm các bản ghi đồng hành + bản ghi bên Trung
                if ($type_pay == 2) {
                    $fundTransactionRelations = $fundTransactionModel
                                        ->where('fund_transaction_id', $fundTransaction->fund_transaction_id)
                                        ->whereNotIn('id', [$fundTransaction->id])
                                        ->get();

                    if (count($fundTransactionRelations)) {
                        foreach ($fundTransactionRelations as $key => $item) {
                            $this->rollbackBalance($item, $fundTypeHoanTien);
                        }
                    }

                    $fundTransactionRelation = $fundTransactionModel->where('id', $fundTransaction->fund_transaction_id)->first();

                    if ($fundTransactionRelation) {
                        $this->rollbackBalance($fundTransactionRelation, $fundTypeHoanTien);
                    }

                }
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            // Nếu là đổi tiền
            if ($type_object == 2 && $type_pay == 2 && $fund_type_pay_code == 2) {
                $customerModel = \App::make('App\Models\Customer');
                $customer = $customerModel->where('code', $fundTransaction->customer_code)->first();

                // Trả tiền lại lại trả khách
                $amount = ($fundTransaction->cn_change + $fundTransaction->fee_customer) * $fundTransaction->fee_customer_ratio;
                $content = 'Hoàn tiền Đổi tiền Việt sang tiền Trung';
                $customer_id = $customer->id;
                $soure = $fundTransaction;

                (new \App\Services\TransactionService())->setTransactionIncrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_REFUND,
                    $content,
                    $customer_id,
                    $soure
                );

                $this->storeNotiWhenCancelChangeMoney($customer, $content);

                // Cập nhật số dư quỹ
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                // Xóa giao dịch thì - đi số tiên giao dịch trương ứng ở tồn quỹ
                $fundDB->decrement('total_money_out', $fundTransaction->money_update);
                $fundDB->increment('current_balance', $fundTransaction->money_update);

                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            // Nếu là xóa rút tiền
            if ($type_object == 2 && $type_pay == 2 && $fund_type_pay_code == 5) {
                $customerModel = \App::make('App\Models\Customer');
                $customer = $customerModel->where('code', $fundTransaction->customer_code)->first();

                // Update cái yêu cầu rút tiền về đã hủy
                $customerWithdrawalModel = \App::make('App\Models\CustomerWithdrawal');
                $customerWithdrawalDB = $customerWithdrawalModel->where('id', $fundTransaction->customer_withdrawal_id)->first();
                if ($customerWithdrawalDB) {
                    $customerWithdrawalDB->status = \App\Constants\CustomerConstant::KEY_WITHDRAWAL_STATUS_CANCEL;
                    $customerWithdrawalDB->save();
                }

                // Trả tiền lại lại trả khách
                $amount = (int)$customerWithdrawalDB->amount;
                $content = 'Hoàn tiền Khách hàng rút tiền';
                $customer_id = $customer->id;
                $soure = $fundTransaction;

                (new \App\Services\TransactionService())->setTransactionIncrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_REFUND,
                    $content,
                    $customer_id,
                    $soure
                );
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            // Nếu là các dạng chi tiền thông thường (người dùng tạo: fund_type_pay_code = 0)
            if ($type_object == 2 && $type_pay == 2 && $fund_type_pay_code == 0) {
                $customerModel = \App::make('App\Models\Customer');
                $customer = $customerModel->where('code', $fundTransaction->customer_code)->first();

                // Kiểm tra fund_type_pay_id phải khớp với loại thu chi
                $fundTypePayModel = \App::make('App\Models\FundTypePay');
                $fundTypePayDB = $fundTypePayModel->where('id', $fundTransaction->fund_type_pay_id)->first();

                // Trả tiền lại lại trả khách
                $amount = (int)$fundTransaction->money_update;
                $content = 'Hoàn tiền ' . $fundTypePayDB->name;
                $customer_id = $customer->id;
                $soure = $fundTransaction;

                (new \App\Services\TransactionService())->setTransactionIncrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_REFUND,
                    $content,
                    $customer_id,
                    $soure
                );
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                $fundDB->increment('total_money_in', $amount);
                $fundDB->increment('current_balance', $amount);

                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);

                ReportCustomer::query()->where('customer_id', $customer_id)->decrement('purchase_amount', $amount);
            }

            if ($type_object == 3 && $fund_type_pay_code == 0) {
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if($type_pay == 1){
                    $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                    $fundDB->decrement('current_balance', $fundTransaction->money_update);
                }
                if($type_pay == 2){
                    $fundDB->increment('total_money_in', $fundTransaction->money_update);
                    $fundDB->increment('current_balance', $fundTransaction->money_update);
                }
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            // Xoa giao dich khach hang chi
            if ($type_object == 2 && $type_pay == 1 && $fund_type_pay_code == 0) {
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                // Kiểm tra fund_type_pay_id phải khớp với loại thu chi
                $fundTypePayModel = \App::make('App\Models\FundTypePay');
                $fundTypePayDB = $fundTypePayModel->where('id', $fundTransaction->fund_type_pay_id)->first();


                // Trả tiền lại lại trả khách
                $amount = (int)$fundTransaction->money_update;
                $content = 'Hoàn tiền ' . $fundTypePayDB->name;
                $customer_id = $customer->id;
                $soure = $fundTransaction;
                (new \App\Services\TransactionService())->setTransactionDecrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_PURCHASE,
                    $content,
                    $customer_id,
                    $soure
                );
                 // Xóa giao dịch thì - đi số tiên giao dịch trương ứng ở tồn quỹ
                 $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                 $fundDB->decrement('current_balance', $fundTransaction->money_update);
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            if ($type_object == 2 && $fund_type_pay_code == 11 && $fundTransaction->customer_code == null) {
                // Cảnh báo
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if ($fundDB && $fundTransaction->money_update > $fundDB->current_balance) {
                    $result = [
                        'unit' => 'Số dư quỹ không đủ để xóa nạp tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
                // Trừ tiền
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if($type_pay == 1){
                    $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                    $fundDB->decrement('current_balance', $fundTransaction->money_update);
                }
                if($type_pay == 2){
                    $fundDB->increment('total_money_in', $fundTransaction->money_update);
                    $fundDB->increment('current_balance', $fundTransaction->money_update);
                }
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            if ($type_object == 2 && $fund_type_pay_code == 11 && $fundTransaction->customer_code != null) {
                // Cảnh báo
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if ($fundDB && $fundTransaction->money_update > $fundDB->current_balance) {
                    $result = [
                        'unit' => 'Số dư quỹ không đủ để xóa nạp tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Check số dư của khách cũ còn đủ để trừ tiền không?
                if ($this->checkCustomerHasEnoughBalance($fundTransaction->customer_code, $fundTransaction->money_update) == false) {
                    $result = [
                        'money' => 'Số dư ví của khách không đủ để cập nhật số tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }

                // Thu lại tiền của khách
                $amount = (int)$fundTransaction->money_update;
                $content = 'Hủy nạp tiền ví điện tử.';
                $customer_id = $customer->id;
                $soure = $fundTransaction;

                (new \App\Services\TransactionService())->setTransactionDecrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                    $content,
                    $customer_id,
                    $soure
                );
                ReportCustomer::query()->where('customer_id', $customer_id)->decrement('deposited_amount', $amount);

                // Trừ tiền
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if($type_pay == 1){
                    $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                    $fundDB->decrement('current_balance', $fundTransaction->money_update);
                }
                if($type_pay == 2){
                    $fundDB->increment('total_money_in', $fundTransaction->money_update);
                    $fundDB->increment('current_balance', $fundTransaction->money_update);
                }
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            if ($type_object == 3  && $fund_type_pay_code == 11) {
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if ($fundDB && $fundTransaction->money_update > $fundDB->current_balance) {
                    $result = [
                        'unit' => 'Số dư quỹ không đủ để xóa nạp tiền'
                    ];
                    return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                }
                // Trừ tiền
                $fundModel = \App::make('App\Models\Fund');
                $fundDB = $fundModel->where('id', $fundTransaction->fund_id)->first();
                if($type_pay == 1){
                    $fundDB->decrement('total_money_in', $fundTransaction->money_update);
                    $fundDB->decrement('current_balance', $fundTransaction->money_update);
                }
                if($type_pay == 2){
                    $fundDB->increment('total_money_in', $fundTransaction->money_update);
                    $fundDB->increment('current_balance', $fundTransaction->money_update);
                }
                // Xóa bản ghi nạp tiền này và lưu log
                $this->deleteFundTransaction($fundTransaction);
            }

            $this->updateBalance($fundTransaction);
            \DB::commit();
            return resSuccess();
        } catch (\Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    private function deleteFundTransaction($fundTransaction)
    {
        // Ghi lại log
        LogFundTransaction::query()->create([
            'fund_transactions' => json_encode($fundTransaction),
            'name' => 'Hủy nạp tiền',
            'user_id' => getCurrentUserId(),
            'user_name' => getCurrentUser()->name,
            'user_email' => getCurrentUser()->email,
        ]);

        $fundTransaction->delete();
    }

    private function updateBalance($fundTransaction)
    {
        $fundTransactionAll = $this->getAllFundTransaction($fundTransaction);
        if(empty($fundTransactionAll)) {
            return false;
        }
        // type_pay = 1 là giao dịch + tiền;
        // type_pay = 2 là giao dịch - tiền;

        foreach($fundTransactionAll as $fund) {
            if($fundTransaction->type_pay == 1) {
                $balance = $fund->balance - $fundTransaction->money_update;
            }

            if($fundTransaction->type_pay == 2) {
                $balance = $fund->balance + $fundTransaction->money_update;
            }
            FundTransaction::where('id', $fund->id)->update(['balance' => $balance]);
        }
    }

    private function storeNotiWhenCancelChangeMoney($customer, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content' => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8009,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customer->id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    private function storeNotiWhenHide($customer, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8002,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customer->id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function rollbackBalance($fundTransaction, $fundTypeHoanTien)
    {
        $data_fund = [
            'type_object'        => $fundTransaction->type_object,
            'customer_code'      => $fundTransaction->customer_code,
            'type_pay'           => $fundTransaction->type_pay == 1 ? 2 : 1,
            'fund_id'            => $fundTransaction->fund_id,

            'fund_type_pay_id'   => $fundTypeHoanTien->id,
            'fund_type_pay_code' => $fundTypeHoanTien->code,

            'money'              => $fundTransaction->money_update,
            'note'               => 'Hủy ' . $fundTransaction->note,
            'lock'               => 1
        ];
        // Không sinh ra bản ghi mới nữa mà chỉ lưu lại log thôi
        // $result = $this->_service->store($data_fund);

        // Khóa nó lại
        $fundTransaction->lock = 1;
        $fundTransaction->save();

        // Thêm ghi log
        $userOnline = \Auth::user();
        $error_logsDB = $fundTransaction->logs == null ? [] : json_decode($fundTransaction->logs);

        $temp_log = [
            'action'             => 'Xóa',

            'user_request_name'  => $userOnline->name,
            'user_request_email' => $userOnline->email,
            'user_request_id'    => $userOnline->id,

            'column'             => "",
            'value_old'          => "",
            'value_new'          => "",

            'time'               => date('Y-m-d H:i:s', time())
        ];

        array_push($error_logsDB, $temp_log);

        $fundTransaction->logs = json_encode($error_logsDB);
        $fundTransaction->save();
    }

    public function hide01022023(string $id)
    {
        $data = request()->all();
        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransaction = $fundTransactionModel->where('id', $id)->where('status', 1)->first();
        if (is_null($fundTransaction)) {
            return resError('Bản ghi không tồn tại');
        }
        try {
            \DB::beginTransaction();
            // Cho nó ẩn đi
            $fundTransaction->status = 0;
            $fundTransaction->save();

            // Cập nhật số tiền tổng
            $fundService = \App::make('App\Services\FundService');;
            $fundService->updateTotalMoney($fundTransaction->fund_id);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateMessage(): array
    {
        return [
            'customer_code.required' => 'Khách hàng không được để trống',
            'note.max'                => 'Ghi chú không được quá 255 kí tự',
            'money_update.min'        => 'Số tiền không được nhỏ hơn 0'
        ];
    }

    public function updateRequest(string $id): array
    {
        return [
            'customer_code'    => 'required|exists:customers,code',
            'money_update'            => 'required|numeric|min:1|max:10000000000',
        ];
    }

    public function update(string $id): JsonResponse
    {
        $this->throwValidationAndAction(__FUNCTION__, $id);

        $data = request()->only([
            'customer_code',
            'money_update'
        ]);

        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransaction = $fundTransactionModel->where('id', $id)
                                            ->where('fund_type_pay_code', 1)
                                            ->where('lock', 0)->first();
        if (is_null($fundTransaction)) {
            return resError('Bản ghi không tồn tại');
        }

        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_code'])->first();

        $data['customer_name'] = $customer->name;
        $data['customer_phone'] = $customer->phone_number;

        try {
            \DB::beginTransaction();

            if ($data['money_update'] != $fundTransaction->money_update) {
                $this->updateBalanceWhenUpdate($fundTransaction, $data['money_update']);
            }
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypeCapNhat = $fundTypePayModel->where('code', 7)->first();
            if (is_null($fundTypeCapNhat)) {
                $result = [
                    'unit' => 'Loại thanh cập nhật số dư chưa được khởi tạo, liên hệ admin'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }


            // Nếu chưa có khách nào thì xử lý bình thường
            if ($fundTransaction->customer_code == null) {
                $result = $this->_service->update($id,$data);

                // Tạo giao dịch nạp tiền cho khách
                $amount = $result->money_update;
                $content = 'Nạp tiền vào ví điện tử';
                $customer_id = $customer->id;
                $soure = $result;

                (new \App\Services\TransactionService())->setTransactionIncrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                    $content,
                    $customer_id,
                    $soure
                );
            }

            // Nếu đã có khách hàng
            if ($fundTransaction->customer_code != null) {
                // Trường hợp khách hàng giữ nguyên và update số tiền
                if ($fundTransaction->customer_code == $data['customer_code'] && $fundTransaction->money_update != $data['money_update']) {
                    // Nếu tiền mới nhiều hơn
                    if ($data['money_update'] > $fundTransaction->money_update) {
                        $moneyDiff = $data['money_update'] - $fundTransaction->money_update;

                        // Cộng tiền vào quỹ
                        $data_fund = [
                            'type_object'                => $fundTransaction->type_object,
                            'customer_code'              => $fundTransaction->customer_code,
                            'type_pay'                   => 1,

                            'fund_id'                    => $fundTransaction->fund_id,
                            'fund_type_pay_id'           => $fundTypeCapNhat->id,
                            'fund_type_pay_code'         => $fundTypeCapNhat->code,

                            'fund_transaction_update_id' => $fundTransaction->id,

                            'money'                      => $moneyDiff,
                            'note'                       => 'Cập nhật số dư',
                            'lock'                       => 1
                        ];
                        // $result = $this->_service->store($data_fund);

                        // Cộng tiền vào ví khách
                        $amount = $moneyDiff;
                        $content = $fundTransaction->note;
                        $customer_id = $customer->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionIncrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                            $content,
                            $customer_id,
                            $soure
                        );

                        // Ghi log
                        $data_log = [
                            'column' => 'Số tiền',
                            'value_old' => $fundTransaction->money_update,
                            'value_new' => $data['money_update'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        // Cập nhật số dư
                        $fundTransaction->money_update = $data['money_update'];
                        $fundTransaction->save();
                    }

                    if ($data['money_update'] < $fundTransaction->money_update) {
                        $moneyDiff = abs($data['money_update'] - $fundTransaction->money_update);

                        // Check quỹ xem đủ tiền để trừ không
                        if ($this->checkFundHasEnoughBalance($fundTransaction->fund_id, $moneyDiff) == false) {
                            $result = [
                                'unit' => 'Số dư quỹ không đủ để để cập nhật số tiền'
                            ];
                            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                        }

                        // Check khách cũ còn đủ tiền để hoàn lại không
                        if ($this->checkCustomerHasEnoughBalance($fundTransaction->customer_code, $moneyDiff) == false) {
                            $result = [
                                'money' => 'Số dư ví của khách không đủ để cập nhật số tiền'
                            ];
                            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                        }


                        // Trừ tiền vào quỹ
                        $data_fund = [
                            'type_object'                => $fundTransaction->type_object,
                            'customer_code'              => $fundTransaction->customer_code,
                            'type_pay'                   => 2,

                            'fund_id'                    => $fundTransaction->fund_id,
                            'fund_type_pay_id'           => $fundTypeCapNhat->id,
                            'fund_type_pay_code'         => $fundTypeCapNhat->code,

                            'fund_transaction_update_id' => $fundTransaction->id,

                            'money'                      => $moneyDiff,
                            'note'                       => 'Cập nhật số dư',
                            'lock'                       => 1
                        ];
                        // $result = $this->_service->store($data_fund);

                        // Trừ tiền vào ví khách
                        $amount = $moneyDiff;
                        $content = "Hủy " . $fundTransaction->note;
                        $customer_id = $customer->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionDecrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                            $content,
                            $customer_id,
                            $soure
                        );

                        // Ghi log
                        $data_log = [
                            'column' => 'Số tiền',
                            'value_old' => $fundTransaction->money_update,
                            'value_new' => $data['money_update'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        // Cập nhật số dư
                        $fundTransaction->money_update = $data['money_update'];
                        $fundTransaction->save();
                    }
                }

                // Trường hợp thay đổi khách hàng và không update số tiền
                if ($fundTransaction->customer_code != $data['customer_code'] && $fundTransaction->money_update == $data['money_update']) {
                    // Check số dư của khách cũ còn đủ để trừ tiền không?
                    if ($this->checkCustomerHasEnoughBalance($fundTransaction->customer_code, $fundTransaction->money_update) == false) {
                        $result = [
                            'money' => 'Số dư ví của khách không đủ để cập nhật số tiền'
                        ];
                        return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                    }

                    // Trừ tiền ở ví khách cũ
                    $customerInFundTransaction = $customerModel->where('code', $fundTransaction->customer_code)->first();
                    $amount = $fundTransaction->money_update;
                    $content = "Hủy " . $fundTransaction->note;
                    $customer_id = $customerInFundTransaction->id;
                    $soure = $fundTransaction;

                    (new \App\Services\TransactionService())->setTransactionDecrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                        $content,
                        $customer_id,
                        $soure
                    );

                    // Cộng tiền vào ví khách mới
                    $amount = $fundTransaction->money_update;
                    $content = $fundTransaction->note;
                    $customer_id = $customer->id;
                    $soure = $fundTransaction;

                    (new \App\Services\TransactionService())->setTransactionIncrement(
                        $amount,
                        \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                        $content,
                        $customer_id,
                        $soure
                    );

                    // Ghi log
                    $data_log = [
                        'column' => 'Mã khách hàng',
                        'value_old' => $fundTransaction->customer_code,
                        'value_new' => $data['customer_code'],
                    ];
                    $this->writeLogFundTransaction($fundTransaction, $data_log);

                    // Cập nhật mã khách hàng
                    $fundTransaction->customer_code = $data['customer_code'];
                    $fundTransaction->customer_name = $customer->name;
                    $fundTransaction->customer_phone = $customer->phone_number;
                    $fundTransaction->save();
                }

                // Trường hợp thay đổi khách hàng và update số tiền
                if ($fundTransaction->customer_code != $data['customer_code'] && $fundTransaction->money_update != $data['money_update']) {
                    // Nếu tiền mới nhiều hơn
                    if ($data['money_update'] > $fundTransaction->money_update) {
                        $moneyDiff = $data['money_update'] - $fundTransaction->money_update;
                        // Check số dư của khách cũ còn đủ để trừ tiền không?
                        if ($this->checkCustomerHasEnoughBalance($fundTransaction->customer_code, $fundTransaction->money_update) == false) {
                            $result = [
                                'money' => 'Số dư ví của khách không đủ để cập nhật số tiền'
                            ];
                            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                        }

                        // Cộng tiền vào quỹ bênh lên cho khách mới
                        $data_fund = [
                            'type_object'                => $fundTransaction->type_object,
                            'customer_code'              => $data['customer_code'],
                            'type_pay'                   => 1,

                            'fund_id'                    => $fundTransaction->fund_id,
                            'fund_type_pay_id'           => $fundTypeCapNhat->id,
                            'fund_type_pay_code'         => $fundTypeCapNhat->code,

                            'fund_transaction_update_id' => $fundTransaction->id,

                            'money'                      => $moneyDiff,
                            'note'                       => 'Cập nhật số dư',
                            'lock'                       => 1
                        ];
                        // $result = $this->_service->store($data_fund);


                        // Trừ tiền ở ví khách cũ
                        $customerInFundTransaction = $customerModel->where('code', $fundTransaction->customer_code)->first();
                        $amount = $fundTransaction->money_update;
                        $content = "Hủy " . $fundTransaction->note;
                        $customer_id = $customerInFundTransaction->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionDecrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                            $content,
                            $customer_id,
                            $soure
                        );

                        // Cộng tiền mới cho khách B
                        $amount = $data['money_update'];
                        $content = $fundTransaction->note;
                        $customer_id = $customer->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionIncrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                            $content,
                            $customer_id,
                            $soure
                        );

                        // Ghi log
                        $data_log = [
                            'column' => 'Số tiền',
                            'value_old' => $fundTransaction->money_update,
                            'value_new' => $data['money_update'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        $data_log = [
                            'column' => 'Mã khách hàng',
                            'value_old' => $fundTransaction->customer_code,
                            'value_new' => $data['customer_code'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        // Cập nhật số dư
                        $fundTransaction->money_update = $data['money_update'];
                        $fundTransaction->customer_code = $data['customer_code'];
                        $fundTransaction->customer_name = $customer->name;
                        $fundTransaction->customer_phone = $customer->phone_number;
                        $fundTransaction->save();
                    }

                    if ($data['money_update'] < $fundTransaction->money_update) {
                        $moneyDiff = abs($data['money_update'] - $fundTransaction->money_update);
                        // Check quỹ xem đủ tiền để trừ không
                        if ($this->checkFundHasEnoughBalance($fundTransaction->fund_id, $moneyDiff) == false) {
                            $result = [
                                'unit' => 'Số dư quỹ không đủ để để cập nhật số tiền'
                            ];
                            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
                        }

                        // Trừ tiền vào quỹ bị thiếu hụt cho khách cũ
                        $data_fund = [
                            'type_object'                => $fundTransaction->type_object,
                            'customer_code'              => $fundTransaction->customer_code,
                            'type_pay'                   => 2,

                            'fund_id'                    => $fundTransaction->fund_id,
                            'fund_type_pay_id'           => $fundTypeCapNhat->id,
                            'fund_type_pay_code'         => $fundTypeCapNhat->code,

                            'fund_transaction_update_id' => $fundTransaction->id,

                            'money'                      => $moneyDiff,
                            'note'                       => 'Cập nhật số dư',
                            'lock'                       => 1
                        ];
                        // $result = $this->_service->store($data_fund);

                        // Trừ tiền ở ví khách cũ
                        $customerInFundTransaction = $customerModel->where('code', $fundTransaction->customer_code)->first();
                        $amount = $fundTransaction->money_update;
                        $content = "Hủy " . $fundTransaction->note;
                        $customer_id = $customerInFundTransaction->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionDecrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                            $content,
                            $customer_id,
                            $soure
                        );


                        // Cộng tiền mới cho khách B
                        $amount = $data['money_update'];
                        $content = $fundTransaction->note;
                        $customer_id = $customer->id;
                        $soure = $fundTransaction;

                        (new \App\Services\TransactionService())->setTransactionIncrement(
                            $amount,
                            \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                            $content,
                            $customer_id,
                            $soure
                        );


                        // Ghi log
                        $data_log = [
                            'column' => 'Số tiền',
                            'value_old' => $fundTransaction->money_update,
                            'value_new' => $data['money_update'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        $data_log = [
                            'column' => 'Mã khách hàng',
                            'value_old' => $fundTransaction->customer_code,
                            'value_new' => $data['customer_code'],
                        ];
                        $this->writeLogFundTransaction($fundTransaction, $data_log);

                        // Cập nhật số dư
                        $fundTransaction->money_update = $data['money_update'];
                        $fundTransaction->customer_code = $data['customer_code'];
                        $fundTransaction->customer_name = $customer->name;
                        $fundTransaction->customer_phone = $customer->phone_number;
                        $fundTransaction->save();
                    }
                }
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    private function getAllFundTransaction($fundTransaction)
    {

        $createdAt = Carbon::parse($fundTransaction->created_at)->format('Y-m-d H:i:s');

        //get toàn bộ giao dịch trước thời điểm cộng trừ tiền;

        return FundTransaction::query()
           ->where('created_at','>=', $createdAt)
           ->where('fund_id', $fundTransaction->fund_id)
           ->where('id', '>', $fundTransaction->id)
           ->where('status', 1)
           ->get();
    }

    private function updateBalanceWhenUpdate($fundTransaction, $moneyUpdate)
    {
        $fundTransactionAll = $this->getAllFundTransaction($fundTransaction);

        if(empty($fundTransactionAll)) {
            return false;
        }

        // type_pay = 1 là giao dịch + tiền;
        // type_pay = 2 là giao dịch - tiền;
        $moneyDifferent = $moneyUpdate - $fundTransaction->money_update;
        foreach($fundTransactionAll as $fund) {
            $balance = $fund->balance + ($moneyUpdate - $fundTransaction->money_update);
            FundTransaction::where('id', $fund->id)->increment('balance', $moneyDifferent);
        }
        // Cập nhật balance của chính giao dịch đó
        FundTransaction::where('id', $fundTransaction->id)->increment('balance', $moneyDifferent);
        // Cập nhật lại balance trong bảng funds
        if($fundTransaction->fund){
            $fundTransaction->fund()->increment('current_balance', $moneyDifferent);

            // Kiểm tra giao dịch thu/chi để cộng trừ tiền tương ứng
            if($fundTransaction->type_pay == 1){
                $fundTransaction->fund()->increment('total_money_in', $moneyDifferent);
            }
            if($fundTransaction->type_pay == 2){
                $fundTransaction->fund()->increment('total_money_out', $moneyDifferent);
            }
        }

        // Ghi lại log
        LogFundTransaction::query()->create([
            'fund_transactions' => json_encode($fundTransaction),
            'name' => 'Update thu chi',
            'user_id' => getCurrentUserId(),
            'user_name' => getCurrentUser()->name,
            'user_email' => getCurrentUser()->email,
        ]);
    }


    public function checkFundHasEnoughBalance($fund_id, $money)
    {
        $fundModel = \App::make('App\Models\Fund');
        $fundDB = $fundModel->where('id', $fund_id)->first();

        if ($fundDB && $fundDB->current_balance > $money) {
            return true;
        }
        return false;
    }

    public function checkCustomerHasEnoughBalance($customer_code, $money)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $customer_code)->first();

        $customerService = \App::make('App\Services\CustomerService');;
        $customerBalance = (int)$customerService->getBalanceAmount($customer->id);

        if ($customerBalance >= $money) {
            return true;
        }
        return false;
    }


    public function writeLogFundTransaction($fundTransaction, $data)
    {
        $userOnline = \Auth::user();
        $error_logsDB = $fundTransaction->logs == null ? [] : json_decode($fundTransaction->logs);

        $temp_log = [
            'action'             => 'Cập nhật',

            'user_request_name'  => $userOnline->name,
            'user_request_email' => $userOnline->email,
            'user_request_id'    => $userOnline->id,

            'column'             => $data['column'],
            'value_old'          => $data['value_old'],
            'value_new'          => $data['value_new'],

            'time'               => date('Y-m-d H:i:s', time())
        ];

        array_push($error_logsDB, $temp_log);

        $fundTransaction->logs = json_encode($error_logsDB, JSON_UNESCAPED_UNICODE);
        $fundTransaction->save();
    }

    public function saveTransferFund(Request $request)
    {
        $validationRules = [
            'fund_id_from' => 'required|exists:funds,id',
            'fund_id_to'   => 'required|exists:funds,id',
            'money'        => 'required|numeric|min:0|max:10000000000'
        ];
        $validationMessages = [
            'money.max' => 'Alo'
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra 2 quỹ phải khách nhau
        if ($data['fund_id_from'] == $data['fund_id_to']) {
            $result = [
                'unit' => 'Không thể chọn quỹ từ và quỹ đến giống nhau'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra 2 kho phải cùng 1 loại tiền tệ
        $fundModel = \App::make('App\Models\Fund');

        $fund_from = $fundModel->where('id', $data['fund_id_from'])->withoutGlobalScopes()->first();
        $fund_to = $fundModel->where('id', $data['fund_id_to'])->withoutGlobalScopes()->first();

        if ($fund_from->unit_currency != $fund_to->unit_currency) {
            $result = [
                'unit' => 'Hai quỹ không cùng đơn vị tiền tệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Chặn chuyển quỹ TQ
        if ($fund_from->unit_currency == 2) {
            $result = [
                'unit' => 'Chưa hỗ trợ chuyển quỹ tiền ¥'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra số tiền chi ra phải có
        if ($data['money'] > $fund_from->current_balance) {
            $result = [
                'money' => 'Quỹ ' . $fund_from->name . ' không đủ số dư, không thể tạo chuyển quỹ'
            ];
            // return resErrorWithinData($result, 'Có lỗi xảy ra', 422); // Logic 12/09/2024 cho phép trừ âm quỹ luôn
        }

        // Lấy ra FundTypePay của chuyển quỹ
        $fundTypePayModel = \App::make('App\Models\FundTypePay');

        $fundChuyenQuy = $fundTypePayModel->where('code', 3)->first();

        if (is_null($fundChuyenQuy)) {
            $result = [
                'unit' => 'Loại thanh toán chuyển quỹ chưa được khởi tạo, liên hệ admin'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Tạo phiếu chi
        $data_from = [
            'type_object'        => 1,
            'type_pay'           => 2,
            'fund_id'            => $fund_from->id,
            'fund_type_pay_id'   => $fundChuyenQuy->id,
            'fund_type_pay_code' => $fundChuyenQuy->code,

            'money'              => $data['money'],
            'note'               => "Chuyển tiền tới quỹ " . $fund_to->name,
        ];

        $data_to = [
            'type_object'        => 1,
            'type_pay'           => 1,
            'fund_id'            => $fund_to->id,
            'fund_type_pay_id'   => $fundChuyenQuy->id,
            'fund_type_pay_code' => $fundChuyenQuy->code,

            'money'              => $data['money'],
            'note'               => "Nhận tiền từ quỹ " . $fund_from->name,
        ];

        try {
            \DB::beginTransaction();

            // Tạo bản ghi
            $result_from = $this->_service->store($data_from);
            $result_to = $this->_service->store($data_to);

            // Cập nhật mã giao dịch của nhau
            $result_from->fund_transaction_id = $result_to->id;
            $result_from->save();

            $result_to->fund_transaction_id = $result_from->id;
            $result_to->save();

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveTransferFund01022023(Request $request)
    {
        $validationRules = [
            'fund_id_from' => 'required|exists:funds,id',
            'fund_id_to'   => 'required|exists:funds,id',
            'money'        => 'required|numeric|min:0|max:10000000000'
        ];
        $validationMessages = [
            'money.max' => 'Alo'
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra 2 quỹ phải khách nhau
        if ($data['fund_id_from'] == $data['fund_id_to']) {
            $result = [
                'unit' => 'Không thể chọn quỹ từ và quỹ đến giống nhau'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra 2 kho phải cùng 1 loại tiền tệ
        $fundModel = \App::make('App\Models\Fund');

        $fund_from = $fundModel->where('id', $data['fund_id_from'])->withoutGlobalScopes()->first();
        $fund_to = $fundModel->where('id', $data['fund_id_to'])->withoutGlobalScopes()->first();

        if ($fund_from->unit_currency != $fund_to->unit_currency) {
            $result = [
                'unit' => 'Hai quỹ không cùng đơn vị tiền tệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra số tiền chi ra phải có
        if ($data['money'] > $fund_from->current_balance) {
            $result = [
                'money' => 'Quỹ ' . $fund_from->name . ' không đủ số dư, không thể tạo chuyển quỹ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Tạo phiếu chi
        $data_from = [
            'type_object'        => 1,

            'type_pay'           => 2,
            'fund_type_pay_id'   => null,

            'fund_id'            => $fund_from->id,
            'fund_type_currency' => $fund_from->type_currency,
            'fund_unit_currency' => $fund_from->unit_currency,

            'money'              => $data['money'],
            'note'               => "Chuyển tiền tới quỹ " . $fund_to->name,
        ];

        $data_to = [
            'type_object'        => 1,

            'type_pay'           => 1,
            'fund_type_pay_id'   => null,

            'fund_id'            => $fund_to->id,
            'fund_type_currency' => $fund_to->type_currency,
            'fund_unit_currency' => $fund_to->unit_currency,

            'money'              => $data['money'],
            'note'               => "Nhận tiền từ quỹ " . $fund_from->name,
        ];


        try {
            \DB::beginTransaction();

            // Tạo bản ghi
            $result_from = $this->_service->store($data_from);
            $result_to = $this->_service->store($data_to);

            // Cập nhật số tiền tổng
            $fundService = \App::make('App\Services\FundService');
            $fundService->updateTotalMoney($fund_from->id);
            $fundService->updateTotalMoney($fund_to->id);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveMoneyChange(Request $request)
    {
        $validationRules = [
            'customer_code'      => 'required|exists:customers,code',

            'cn_change'          => 'required|numeric|min:1|max:10000000000',

            'fee_customer'       => 'required|numeric|min:1|max:10000000000',
            'fee_customer_ratio' => 'required|numeric|min:1|max:10000000000',
            'fee_system'         => 'required|numeric|min:0|max:10000000000',
            'fee_system_ratio'   => 'required|numeric|min:1|max:10000000000',

            'fund_id'            => 'required|exists:funds,id',
        ];
        $validationMessages = [
            'fee_customer.min'       => 'Phí khách hàng tối thiểu là 1',
            'fee_customer_ratio.min' => 'Tỉ giá Phí khách hàng tối thiểu là 1',
            'fee_system.min'         => 'Phí hệ thống tối thiểu là 0',
            'fee_system_ratio.min'   => 'Tỉ giá Phí hệ thống tối thiểu là 0',
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra xem ngân hàng xuất tiền đúng là của TQ không
        $fundModel = \App::make('App\Models\Fund');

        $fundDB = $fundModel->where('id', $data['fund_id'])
                                ->where('unit_currency', 2)
                                ->first();
        if (is_null($fundDB)) {
            $result = [
                'unit' => 'Đây không phải quỹ TQ, vui lòng kiểm tra lại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra xem nó có đủ tiền để thực hiện giao dịch này này không
        if (($data['cn_change'] + $data['fee_system']) > $fundDB->current_balance) {
            $result = [
                'money' => 'Quỹ ' . $fundDB->name . ' không đủ số dư'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Tính tổng tiền khách phải trả để đổi
        $configModel = \App::make('App\Models\Config');
        $exchange_rateDB = $configModel->where('key', 'exchange_rate')->first();

        if (is_null($exchange_rateDB)) {
            $result = [
                'money' => 'Không lấy được tỉ giá'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $exchange_rate = (int) $exchange_rateDB->value;

        $total_money = ($data['cn_change'] + $data['fee_customer']) * $data['fee_customer_ratio'];

        // Kiểm tra xem khách hàng có đủ số dư để trừ không
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_code'])->first();

        $customerService = \App::make('App\Services\CustomerService');;
        $customerBalance = (int)$customerService->getBalanceAmount($customer->id);

        if ($customerBalance < $total_money) {
            $result = [
                'money' => 'Số dư ví của khách không đủ để thực hiện giao dịch'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Lấy ra FundTypePay nạp tiền TQ
        $fundTypePayModel = \App::make('App\Models\FundTypePay');

        $fundChi = $fundTypePayModel->where('code', 2)->first();

        if (is_null($fundChi)) {
            $result = [
                'unit' => 'Loại thanh toán chi Đổi tiền chưa được khởi tạo, liên hệ admin'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Tạo phiếu chi cho quỹ
            $content = 'Đổi RMB: ' . $data['cn_change'] . '	¥';
            $dataChiQuy = [
                'type_object'        => 2,
                'customer_code'      => $data['customer_code'],

                'type_pay'           => 2,
                'fund_type_pay_id'   => $fundChi->id,
                'fund_type_pay_code' => $fundChi->code,

                'fund_id'            => $fundDB->id,

                'money'              => $data['cn_change'] + $data['fee_system'],

                'fee_customer'       => $data['fee_customer'],
                'fee_customer_ratio' => $data['fee_customer_ratio'],
                'fee_system'         => $data['fee_system'],
                'fee_system_ratio'   => $data['fee_system_ratio'],

                'cn_change'          => $data['cn_change'],

                'note'               => $content,
            ];

            $result_1 = $this->_service->store($dataChiQuy);

            // Trừ tiền của khách
            $amount = $total_money;
            $customer_id = $customer->id;
            $soure = $result_1;

            (new \App\Services\TransactionService())->setTransactionDecrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_EXCHANGE,
                $content,
                $customer_id,
                $soure
            );
            // Thêm noti cho customer
            $this->storeNotiWhenSaveMoneyChange($customer, $content);

            \DB::commit();
            return resSuccessWithinData($result_1);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    private function storeNotiWhenSaveMoneyChange($customer, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content' => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8007,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $customer->id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function saveMoneyChange31012022(Request $request)
    {
        $validationRules = [
            'customer_code'      => 'required|exists:customers,code',

            'cn_change'          => 'required|numeric|min:1|max:10000000000',

            'fee_customer'       => 'required|numeric|min:1|max:10000000000',
            'fee_customer_ratio' => 'required|numeric|min:1|max:10000000000',
            'fee_system'         => 'required|numeric|min:1|max:10000000000',
            'fee_system_ratio'   => 'required|numeric|min:1|max:10000000000',

            'fund_id'            => 'required|exists:funds,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra xem ngân hàng xuất tiền đúng là của TQ không
        $fundModel = \App::make('App\Models\Fund');

        $fundDB = $fundModel->where('id', $data['fund_id'])
                                ->where('unit_currency', 2)
                                ->first();
        if (is_null($fundDB)) {
            $result = [
                'unit' => 'Đây không phải quỹ TQ, vui lòng kiểm tra lại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra xem nó có đủ tiền để thực hiện giao dịch này này không
        if ($data['cn_change'] > $fundDB->current_balance) {
            $result = [
                'money' => 'Quỹ ' . $fundDB->name . ' không đủ số dư'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Tính tổng tiền khách phải trả để đổi
        $configModel = \App::make('App\Models\Config');
        $exchange_rateDB = $configModel->where('key', 'exchange_rate')->first();

        if (is_null($exchange_rateDB)) {
            $result = [
                'money' => 'Không lấy được tỉ giá'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        $exchange_rate = (int) $exchange_rateDB->value;

        $total_money = $data['cn_change'] * $exchange_rate;
        $total_money +=  $data['fee_customer'] * $data['fee_customer_ratio'];
        $total_money +=  $data['fee_system'] * $data['fee_system_ratio'];

        // Kiểm tra xem khách hàng có đủ số dư để trừ không
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $data['customer_code'])->first();

        $customerService = \App::make('App\Services\CustomerService');;
        $customerBalance = (int)$customerService->getBalanceAmount($customer->id);

        if ($customerBalance < $total_money) {
            $result = [
                'money' => 'Số dư ví của khách không đủ để thực hiện giao dịch'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Lấy ra FundTypePay nạp tiền TQ
        $fundTypePayModel = \App::make('App\Models\FundTypePay');

        $fundChi = $fundTypePayModel->where('code', 2)->first();

        if (is_null($fundChi)) {
            $result = [
                'unit' => 'Loại thanh toán chi Đổi tiền chưa được khởi tạo, liên hệ admin'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Tạo phiếu chi cho quỹ
            $dataChiQuy = [
                'type_object'        => 2,
                'customer_code'      => $data['customer_code'],

                'type_pay'           => 2,
                'fund_type_pay_id'   => $fundChi->id,

                'fund_id'            => $fundDB->id,
                'fund_type_currency' => $fundDB->type_currency,
                'fund_unit_currency' => $fundDB->unit_currency,

                'money'              => $data['cn_change'],

                'fee_customer'       => $data['fee_customer'],
                'fee_customer_ratio' => $data['fee_customer_ratio'],
                'fee_system'         => $data['fee_system'],
                'fee_system_ratio'   => $data['fee_system_ratio'],

                'cn_change'          => $data['cn_change'],
                'cn_change_ratio'    => $exchange_rate,

                'note'               => "Đổi tiền cho khách hàng " . $customer->code,
            ];

            $result_1 = $this->_service->store($dataChiQuy);

            // Trừ tiền của khách
            $amount = $total_money;
            $content = 'Đổi tiền Việt sang tiền Trung';
            $customer_id = $customer->id;
            $soure = $result_1;

            (new \App\Services\TransactionService())->setTransactionDecrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_EXCHANGE,
                $content,
                $customer_id,
                $soure
            );

            \DB::commit();
            return resSuccessWithinData($result_1);
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function saveMoneyReCharge(Request $request)
    {
        $validationRules = [
            'cn_change'            => 'required|numeric|min:0|max:10000000000',
            'cn_change_ratio'      => 'required|numeric|min:0|max:10000000000',

            'fee_change_cn'        => 'required|numeric|min:0|max:10000000000',
            'fee_change_vnd'       => 'required|numeric|min:0|max:10000000000',

            'fund_id_from'         => 'required|array',
            'fund_id_from.*.id'    => 'required|exists:funds,id',
            'fund_id_from.*.money' => 'required|numeric|min:1|max:10000000000',

            'fund_id_to'           => 'required|exists:funds,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra toàn bộ các quỹ chi ra là từ quỹ VN
        $fundModel = \App::make('App\Models\Fund');
        foreach ($data['fund_id_from'] as $key => $fund) {
            $fundDB = $fundModel->where('id', $fund['id'])
                                    ->where('unit_currency', 1)
                                    ->first();
            if (is_null($fundDB)) {
                $result = [
                    'unit' => 'Quỹ chi tiền phải là quỹ VN'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            // Kiểm tra xem quỹ ý đủ tiền chi ra khônng
            if ($fund['money'] > $fundDB->current_balance && $fundDB->type_currency != Fund::VIRTUAL_TYPE_CURRENCY) {
                $result = [
                    'money' => 'Quỹ ' . $fundDB->name . ' không đủ số dư'
                ];
                // return resErrorWithinData($result, 'Có lỗi xảy ra', 422); // Logic 12/09/2024 cho phép trừ âm quỹ luôn
            }
        }

        // Kiểm tra quỹ nhận tiền phải là quỹ China
        $fundDB = $fundModel->where('id', $data['fund_id_to'])
                                ->where('unit_currency', 2)
                                ->first();

        if (is_null($fundDB)) {
            $result = [
                'unit' => 'Quỹ nhận không phải quỹ TQ, vui lòng kiểm tra lại'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra tồng tiền nó có khớp không

        $total_money_in_array = 0;
        foreach ($data['fund_id_from'] as $key => $fund) {
            $total_money_in_array += $fund['money'];
        }

        $total_money_cn = ($total_money_in_array - $data['fee_change_vnd']) /$data['cn_change_ratio']
            - $data['fee_change_cn'];

        if (round($total_money_cn, 2) != $data['cn_change']) {
            $result = [
                'unit' => 'Tổng tiền chi ra ở các quỹ không trùng với tổng tiền đổi'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Lấy ra FundTypePay nạp tiền TQ
        $fundTypePayModel = \App::make('App\Models\FundTypePay');

        $fundThuT1 = $fundTypePayModel->where('code', 4)->first();

        if (is_null($fundThuT1)) {
            $result = [
                'unit' => 'Loại thanh toán Nạp tiền ngân hàng Trung Quốc ko có, liên hệ admin'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            $fundService = \App::make('App\Services\FundService');

            // Tạo khoản nhận vào cho quỹ Trung
            $fund_to = $fundModel->where('id', $data['fund_id_to'])->first();
            $data_to = [
                'type_object'        => 3,

                'type_pay'           => 1,
                'fund_type_pay_id'   => $fundThuT1->id,
                'fund_type_pay_code' => $fundThuT1->code,

                'fund_id'            => $fund_to->id,

                'cn_change'          => $data['cn_change'],
                'cn_change_ratio'    => $data['cn_change_ratio'],
                'fee_change_cn'      => $data['fee_change_cn'],
                'fee_change_vnd'     => $data['fee_change_vnd'],

                'money'              => $data['cn_change'],

                'note'               => "Nạp tiền ngân hàng Trung Quốc",
            ];

            $result_to = $this->_service->store($data_to);

            // Tạo khoản chi ra cho từng quỹ Việt
            foreach ($data['fund_id_from'] as $key => $fund) {
                $data_from = [
                    'type_object'         => 3,

                    'type_pay'            => 2,
                    'fund_type_pay_id'    => $fundThuT1->id,
                    'fund_type_pay_code'  => $fundThuT1->code,

                    'fund_id'             => $fund['id'],

                    'fund_transaction_id' => $result_to->id,

                    'money'               => $fund['money'],
                    'note'                => "Nạp tiền vào quỹ " . $fund_to->name,
                ];

                $result_from = $this->_service->store($data_from);
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }

    }

    public function saveMoneyReChargeGetCategory(Request $request)
    {
        $fundModel = \App::make('App\Models\Fund');
        $funds = $fundModel->all();

        // Thêm theo quyền
        $userOnline = \Auth::user();
        $hasPerrReserveAll = $userOnline->checkUserHasPer('fund.view-reserve-all');
        $hasPerrReserveWarehouse = $userOnline->checkUserHasPer('fund.view-reserve-warehouse');

        // Nếu không dc tích 1 trong 2 quyền xem thì funds = [];
        if ($hasPerrReserveAll == false && $hasPerrReserveWarehouse == false) {
            $funds = [];
        }
        // Nếu chỉ có quyền kho
        if ($hasPerrReserveWarehouse) {
            $funds = $fundModel->where('type_common', Fund::TYPE_COMMON_WAREHOUSE)->get();
            // Logic 04-12-24: Logic lọc theo kho làm việc của User
            $departmentDB = $userOnline->department;
            if (!is_null($departmentDB) && $departmentDB->warehouse_id > 0) {
                $funds = $funds->where('warehouse_id', $departmentDB->warehouse_id);
            }
        }

        // Nếu chỉ có quyền xem tất cả
        if ($hasPerrReserveAll) {
            $funds = $fundModel->all();
        }

        $arrFundVN = [];
        $arrFundCN = [];

        if ($funds) {
            foreach ($funds as $key => $fund) {
                $temp = [
                    'id'              => $fund->id,
                    'name'            => $fund->name,
                    'current_balance' => $fund->current_balance,
                    'money'           => '',
                    'checked'         => false
                ];
                $temp2 = [
                    'id' => $fund->id,
                    'name' => $fund->name,
                ];
                if ($fund->unit_currency == Fund::VND_UNIT_CURRENCY && ($fund->current_balance || $fund->type_currency == Fund::VIRTUAL_TYPE_CURRENCY)) {
                    array_push($arrFundVN, $temp);
                }
                if ($fund->unit_currency == Fund::CHINESE_UNIT_CURRENCY) {
                    array_push($arrFundCN, $temp2);
                }
            }
        }

        $data_result = [
            'arrFundVN' => $arrFundVN,
            'arrFundCN' => $arrFundCN,
        ];

        return resSuccessWithinData($data_result);
    }

    public function saveMoneyWithdrawal(Request $request)
    {
        $validationRules = [
            'customer_withdrawal_id' => 'required|exists:customer_withdrawal,id',
            'fund_ids'        => 'array',
            'image' => 'string|max:255|nullable',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();
        $totalBalanceTemp = array_sum(array_column($data['fund_ids'], 'balance'));

        // Kiểm tra yêu cầu rút tiền này là chờ xử lý mới được phép xử lý tiếp
        $customerWithdrawalModel = \App::make('App\Models\CustomerWithdrawal');
        $customerWithdrawalDB = $customerWithdrawalModel->where('id', $data['customer_withdrawal_id'])->first();

        $arr_done = [
            \App\Constants\CustomerConstant::KEY_WITHDRAWAL_STATUS_DONE,
            \App\Constants\CustomerConstant::KEY_WITHDRAWAL_STATUS_CANCEL,
        ];
        if (in_array($customerWithdrawalDB->status, $arr_done)) {
            $result = [
                'unit' => 'Yêu cầu này đã được xử lý'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Kiểm tra quỹ chi tiền phải là quỹ Việt Nam
        $fundModel = \App::make('App\Models\Fund');
        foreach($data['fund_ids'] as $fund) {
            $fundDB = $fundModel->where('id', $fund['id'])
                ->where('unit_currency', 1)
                ->first();
            if($fundDB->current_balance < $fund['balance']) {
                $result = [
                    'unit' => 'Số tiền ở quỹ không bằng số tiền khách yêu cầu rút'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            if (is_null($fundDB) || ($fundDB && $fundDB->type_currency == Fund::VIRTUAL_TYPE_CURRENCY)) {
                $result = [
                    'unit' => 'Quỹ chi tiền không phải quỹ VN, vui lòng kiểm tra lại'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        // Kiểm tra quỹ chi có đủ số dư để chi không
        if ($totalBalanceTemp < $customerWithdrawalDB->amount) {
            $result = [
                'unit' => 'Số tiền ở quỹ không bằng số tiền khách yêu cầu rút'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        // Lấy thông tin khách hàng
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('id', $customerWithdrawalDB->customer_id)->first();

        // Lấy ra FundTypePay khách hàng rút tiền
        $fundTypePayModel = \App::make('App\Models\FundTypePay');

        $fundChiT1 = $fundTypePayModel->where('code', 5)->first();

        if (is_null($fundChiT1)) {
            $result = [
                'unit' => 'Loại thanh toán khách hàng rút tiền ko có, liên hệ admin'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Tạo bản ghi trừ tiền từ quỹ
            foreach($data['fund_ids'] as $fundId) {
                $dataChiQuy = [
                    'type_object'        => 2,
                    'customer_code'      => $customer->code,
                    'type_pay'           => 2,
                    'fund_type_pay_id'   => $fundChiT1->id,
                    'fund_type_pay_code' => $fundChiT1->code,
                    'customer_withdrawal_id' => $data['customer_withdrawal_id'],
                    'fund_id'            => $fundId['id'],
                    'money'              => (int)$fundId['balance'],
                    'note'               => "Thanh toán yêu cầu rút tiền " . $customerWithdrawalDB->code . " của khách hàng " . $customer->code,
                ];
                $result_1 = $this->_service->store($dataChiQuy);
            }

            // Cập nhật trạng thái của yêu cầu rút tiền về hoàn thành
            $customerWithdrawalDB->image = $data['image'];
            $customerWithdrawalDB->status = \App\Constants\CustomerConstant::KEY_WITHDRAWAL_STATUS_DONE;
            $customerWithdrawalDB->save();

            $statusName = \App\Constants\CustomerConstant::WITHDRAWAL_STATUSES[$customerWithdrawalDB->status];
            $content = 'Yêu cầu rút tiền được chuyển sang trạng thái ' . $statusName;
             // push notification for client
            $this->updateNotification($customerWithdrawalDB, $content);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }

    }

    private function updateNotification($withdrawal, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content'  => $content
        ];
        $data = [
            'group'           => 8,
            'name'            => 8005,
            'data'            => json_encode($data_customer),
            'time_create'     => time(),
            'customer_id'     => $withdrawal->customer_id,
            'status'          => 1
        ];

        $notificationService->store($data);
    }

    public function listSupplierOrderDetails(Request $request)
    {
        $data = $request->all();
        $per_page = $request->get('per_page', 25);

        $orderModel = \App::make('App\Models\Order');

        $code = $request->code;
        $customer_code = $request->customer_code;
        $staff_order_id = $request->staff_order_id;
        $ecommerce = $request->ecommerce;
        $codePo = $request->code_po;
        $arrCodePo = [];
        $status = $request->status;
        if($codePo) {
            $arrCodePo = explode(';', $codePo);
        }
        $model  = $orderModel;

        $model = $orderModel->select('orders.id', 'orders.code', 'orders.updated_at', 'orders.exchange_rate',
        'orders.order_cost_real', 'orders.china_shipping_cost_real', 'orders.ecommerce', 'orders.status', 'orders.staff_order_id',
        'orders.order_cost', 'orders.china_shipping_cost', 'orders.bonus', 'orders.organization_id', 'orders.deposit_percent', 'orders.customer_id', 'orders.date_ordered'
        )->with([
            'packages' => function($q) {
                return $q->select('id', 'order_id', 'code_po')->whereNotNull('code_po');
            },
            'staffOrder' => function($q) {
                return $q->select('id', 'name');
            },
            'orderStatusTime',
            'customer' => function($q) {
                return $q->select('id', 'source');
            },
        ]);
        if (!is_null($code)) {
            $model = $model->where('orders.code', $code);
        }
        if (!is_null($staff_order_id)) {
            $model = $model->where('orders.staff_order_id', $staff_order_id);
        }
        if (request()->input('organization_id')) {
            $model = $model->where('orders.organization_id', request()->input('organization_id'));
        }
        if (!is_null($ecommerce)) {
            $model = $model->where('orders.ecommerce', $ecommerce);
        }
        if(!empty($arrCodePo)) {
            $model = $model->whereHas('packages', function ($subQuery) use ($arrCodePo){
                $subQuery->where('order_package.order_type', Order::class)
                    ->whereIn('order_package.code_po', $arrCodePo);
            });
        }

        if (!is_null($customer_code)) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $customer_code)->orWhere('phone_number', $customer_code)->first();
            if ($customer) {
                $model = $model->where('customer_id', $customer->id);
            } else {
                $model = $model->where('customer_id', 0);
            }
        }
        $dateOrdered = $request->date_ordered;
        $dateDone= $request->date_done;
        $dateWaitToPay = $request->date_wait_to_pay;
        if (!is_null($dateOrdered) && is_array($dateOrdered)) {
            $start = $dateOrdered[0]. ' 00:00:00';
            $end = $dateOrdered[1]. ' 23:59:59';
            $model = $model->whereBetween('orders.date_ordered', [$start, $end]);
        }
        if (!is_null($dateDone) && is_array($dateDone)) {
            $start = $dateDone[0]. ' 00:00:00';
            $end = $dateDone[1]. ' 23:59:59';
            $model = $model->whereBetween('orders.date_done', [$start, $end]);
        }
        if (!is_null($dateWaitToPay) && is_array($dateWaitToPay)) {
            $start = $dateWaitToPay[0]. ' 00:00:00';
            $end = $dateWaitToPay[1]. ' 23:59:59';
            $model = $model->whereHas('orderStatusTime', function ($subQuery) use ($start, $end){
                $subQuery->whereBetween('status_8', [$start, $end]);
            });
        }
        if (!is_null($status)) {
            $model = $model->where('orders.status', $status);
        }
        $model = $model->orderBy('orders.updated_at', 'desc');
        $querySum = $model->clone()
        ->select(DB::raw('
                        SUM(orders.order_cost/orders.exchange_rate + orders.china_shipping_cost) as total_order_cost,
                        SUM(orders.order_cost_real/orders.exchange_rate + orders.china_shipping_cost_real) as total_order_cost_real
                        '))->first();
        $orders = $model->paginate($per_page);
        $extra = [
            'total_cost' => $querySum->total_order_cost ? $querySum->total_order_cost : 0,
            'total_cost_real' => $querySum->total_order_cost_real ? $querySum->total_order_cost_real : 0
        ];

        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\Order\OrderAccountantResource::class;
        return resSuccessWithinData(new $_paginateResource($orders, $_resource, $extra));
    }

    public function orderConfirmation(Request $request)
    {
        $validationRules = [
            'arr_order_id'   => 'required|array',
            'arr_order_id.*' => 'required|exists:orders,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra các đơn ở trạng thái chờ thanh toán thì mới dc cho đi tiieep
        $orderModel = \App::make('App\Models\Order');

        foreach ($data['arr_order_id'] as $key => $order_id) {
            $orderDB = $orderModel->where('id', $order_id)->first();
            if ($orderDB->status != \App\Constants\OrderConstant::KEY_STATUS_WAIT_TO_PAY) {
                $result = [
                    'unit' => 'Đơn hàng ' . $orderDB->code . ' không hợp lệ'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }

            $diff_order_and_shipping_cost = $this->getDiff($orderDB);
            if ($diff_order_and_shipping_cost < 0) {
                $result = [
                    'unit' => 'Tiền bị chênh.'
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
        }

        // Xử lý
        try {
            \DB::beginTransaction();
            $logService = \App::make('App\Services\ActivityService');
            $userOnline = \Auth::user();
            $now = Carbon::now();
            foreach ($data['arr_order_id'] as $key => $order_id) {
                $orderDB = $orderModel->where('id', $order_id)->first();
                $orderDB->status = \App\Constants\OrderConstant::KEY_STATUS_ORDERED;
                $orderDB->date_ordered = $now;
                $orderDB->save();

                $content = $userOnline->name . ' xác nhận đặt hàng cho đơn hàng: ' . $orderDB->code;
                $logService->setOrderLog($userOnline, $content, $order_id);

                // Thêm noti
                $this->updateOrderNotiLogicInFund($orderDB);
            }

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function getDiff($order)
    {
        /**
         * Công thức
         * Tiền hàng đã trả + Phí TQ đã trả - (Tiền hàng thực tế + phí ship TQ thực tế)
        */
        $orderCostReal = round((float)$order->order_cost_real / $order->exchange_rate, 2); // Tiền hàng thực tế
        $chinaShippingCostReal = (float)$order->china_shipping_cost_real; // phí ship TQ thực tế
        $orderCostOld = round((float)$order->order_cost / $order->exchange_rate, 2); // Tiền hàng đã trả
        $chinaShippingCostOld = (float)$order->china_shipping_cost; //  Phí TQ đã trả
        $bonus = (float)$order->bonus;

        return $orderCostOld + $chinaShippingCostOld  - ($orderCostReal + $chinaShippingCostReal) + $bonus;
    }

    public function updateOrderNotiLogicInFund($order)
    {
        $status = $order->status;

        $staff_care_id      = $order->staff_care_id;
        $staff_counselor_id = $order->staff_counselor_id;
        $staff_order_id     = $order->staff_order_id;

        $notificationService = \App::make('App\Services\NotificationService');

        $data_order = [
            'order_code'       => $order->code,
            'order_id'         => $order->id,
            'order_status'     => $order->status,
            'order_status_txt' => \App\Constants\OrderConstant::STATUSES[$status],
            'from'             => 'orderConfirmation'
        ];

        $name = 2006;

        $data = [
            'group'           => 2,
            'name'            => $name,
            'data'            => json_encode($data_order),
            'time_create'     => time(),
            'status'          => 1
        ];

        if ($staff_care_id) {
            $notificationService->storeNoti($staff_care_id, $data);
        }
        if ($staff_counselor_id) {
            $notificationService->storeNoti($staff_counselor_id, $data);
        }
        if ($staff_order_id) {
            $notificationService->storeNoti($staff_order_id, $data);
        }
    }

    public function orderError(Request $request)
    {
        $validationRules = [
            'order_id'  => 'required|exists:orders,id',
            'reason_id' => 'required|in:1,2',
            'note'      => 'nullable|max:255',
        ];
        $validationMessages = [
            'note.max' => 'Ghi chú không được quá 255 kí tự'
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra các đơn ở trạng thái chờ thanh toán thì mới dc cho đi tiieep
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $data['order_id'])->first();
        if ($orderDB->status != \App\Constants\OrderConstant::KEY_STATUS_WAIT_TO_PAY) {
            $result = [
                'unit' => 'Đơn hàng ' . $orderDB->code . ' không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        // Xử lý
        try {
            \DB::beginTransaction();

            $userOnline = \Auth::user();
            $reson_text = '';
            if ($data['reason_id'] == 1) {
                $reson_text = 'Sai tiền';
            }
            if ($data['reason_id'] == 2) {
                $reson_text = 'Sai mã đặt hàng';
            }
            $content = $userOnline->name . ' tạo báo lỗi với lý do: ' . $reson_text;

            if ($data['note']) {
                $content .= ' (' . $data['note'] . ')';
            }

            // Chuyển trạng thái về đang đặt hàng
            // $orderDB->status = \App\Constants\OrderConstant::KEY_STATUS_ORDERING;

            // Logic mới chuyển về đã đặt cọc
            $orderDB->status = OrderConstant::KEY_STATUS_DEPOSITED;

            // Cập nhật error_log
            $error_logsDB = $orderDB->error_logs == null ? [] : array_reverse(json_decode($orderDB->error_logs));
            $temp_log = [
                'user_request_name'  => $userOnline->name,
                'user_request_email' => $userOnline->email,
                'user_request_id'    => $userOnline->id,

                'reson_text'         => $reson_text,
                'note'               => "",

                'time'               => date('Y-m-d H:i:s', time())
            ];
            if ($data['note']) {
                $temp_log['note'] = $data['note'];
            }
            array_push($error_logsDB, $temp_log);

            $orderDB->error_logs = json_encode($error_logsDB);

            // Lưu lại cho đơn

            $orderDB->save();

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function orderError06022023(Request $request)
    {
        $validationRules = [
            'order_id'  => 'required|exists:orders,id',
            'reason_id' => 'required|in:1,2',
            'note'      => 'nullable|max:255',
        ];
        $validationMessages = [
            'note.max' => 'Ghi chú không được quá 255 kí tự'
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $data = $request->all();

        // Kiểm tra các đơn ở trạng thái chờ thanh toán thì mới dc cho đi tiieep
        $orderModel = \App::make('App\Models\Order');
        $orderDB = $orderModel->where('id', $data['order_id'])->first();
        if ($orderDB->status != \App\Constants\OrderConstant::KEY_STATUS_WAIT_TO_PAY) {
            $result = [
                'unit' => 'Đơn hàng ' . $orderDB->code . ' không hợp lệ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }
        // Xử lý
        try {
            \DB::beginTransaction();

            $userOnline = \Auth::user();
            $reson_text = '';
            if ($data['reason_id'] == 1) {
                $reson_text = 'Sai tiền';
            }
            if ($data['reason_id'] == 2) {
                $reson_text = 'Sai mã đặt hàng';
            }
            $content = $userOnline->name . ' tạo báo lỗi với lý do: ' . $reson_text;

            if ($data['note']) {
                $content .= ' (' . $data['note'] . ')';
            }

            $logService = \App::make('App\Services\ActivityService');
            $logService->setOrderLog($userOnline, $content, $data['order_id']);
            dd('12321');
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function fakeSave(Request $request)
    {
        try {
            \DB::beginTransaction();

            // Trừ tiền của quỹ
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypeNapTien = $fundTypePayModel->where('code', 1)->first();


            $fundModel = \App::make('App\Models\Fund');
            $fund = $fundModel->where('unit_currency', 1)->first();

            $data_to = [
                'type_object'        => 2,
                'customer_code'      => null,
                'type_pay'           => 1,
                'fund_type_pay_id'   => $fundTypeNapTien->id,
                'fund_type_pay_code' => $fundTypeNapTien->code,

                'fund_id'            => $fund->id,

                'money'              => 1000000,

                'note'               => "Khách hàng nạp tiền banking",
            ];

            $result_to = $this->_service->store($data_to);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function export()
    {
        try {
           $data = $this->_service->export();
           return Excel::download(new FundTransactionExport($data), 'fund_transaction.xlsx');
        } catch (\Exception $e) {
            \Log::info($e);
            return resError();
        }
    }

    public function converKeyToNameTransaction($key)
    {
        $list = [
            'type_object'      => 'Đối tượng',
            'customer_code'    => 'Khách hàng',
            'fund_type_pay_id' => 'Loại thu chi',
            'note'             => 'Nội dung',
            'money_update'     => 'Số tiền',
        ];
        return array_key_exists($key, $list) ? $list[$key] : $key;
    }

    public function getListTypeObject($key)
    {
        $list = [
            FundTransaction::CUSTOMER_TYPE_OBJECT => 'Khách hàng',
            FundTransaction::PRIVATE_TYPE_OBJECT  => 'Nội bộ',
        ];
        return array_key_exists($key, $list) ? $list[$key] : $key;
    }

    public function getFundTypePay($id)
    {
        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $fundTypePayDB = $fundTypePayModel->where('id', $id)->first();
        if ($fundTypePayDB) {
            return $fundTypePayDB->name;
        }
        return $id;
    }

    // Ghi log theo nhóm
    // $params : Giá trị mới
    public function writeLogFundTransactionGroup($params, $fundTransactionDB)
    {
        foreach ($params as $key => $item) {
            if ($fundTransactionDB->$key != $params[$key]) {
                // Ghi log
                $data_log = [
                    'column' => $this->converKeyToNameTransaction($key),
                    'value_old' => $fundTransactionDB->$key,
                    'value_new' => $params[$key],
                ];

                if ($key == 'type_object') {
                    $data_log['value_old'] = $this->getListTypeObject($fundTransactionDB->$key);
                    $data_log['value_new'] = $this->getListTypeObject($params[$key]);
                }

                if ($key == 'fund_type_pay_id') {
                    $data_log['value_old'] = $this->getFundTypePay($fundTransactionDB->$key);
                    $data_log['value_new'] = $this->getFundTypePay($params[$key]);
                }

                if ($key == 'customer_code') {
                    $key1 = strtolower($fundTransactionDB->$key);
                    $key2 = strtolower($params[$key]);
                    if ($key1 == $key2) {
                        continue;
                    }
                }
                // dump($data_log);
                $this->writeLogFundTransaction($fundTransactionDB, $data_log);
            }
        }
    }

    public function updateWaitConfirm(Request $request)
    {
        $validationRules = [
            'fund_transaction_id' => 'required|exists:fund_transactions,id',
            'type_object'         => 'required|in:2,3',
            'customer_code'       => 'required_if:type_object,==,2',
            'fund_type_pay_id'    => 'required|exists:fund_type_pays,id',
            'note'                => 'required',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransactionDB = $fundTransactionModel->where('id', $params['fund_transaction_id'])
                                            ->where('fund_type_pay_id', 16)
                                            ->where('lock', 0)->first();
        if (is_null($fundTransactionDB)) {
            return resError('Bản ghi không tồn tại');
        }

        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $customerModel = \App::make('App\Models\Customer');
        $fundTypePayDB = $fundTypePayModel->where('id', $params['fund_type_pay_id'])->first();

        // Nếu là khách hàng thì check customer_code hợp lệ
        if ($params['type_object'] == 2) {
            $customer = $customerModel->where('code', $params['customer_code'])->first();
            if (is_null($customer)) {
                return resError('Khách hàng không tồn tại');
            }
        }

        // Chặn không cho update kiểu này
        if ($params['type_object'] == 3 && $params['fund_type_pay_id'] == 1) {
            $result = [
                'unit' => 'Không được phép chọn khách hàng nạp tiền khi đối tượng là nội bộ'
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();

            // Ghi log
            $params = $request->only([
                'type_object',
                'customer_code',
                'fund_type_pay_id',
                'note',
            ]);
            // Chế lại data chút cho chắc chắn
            if ($params['type_object'] == 3) {
                $params['customer_code'] = null;
            }
            $this->writeLogFundTransactionGroup($params, $fundTransactionDB);


            $type_object = $params['type_object'];

            // Nếu là nội bộ và chưa có thông tin khách hàng
            if ($type_object == FundTransaction::PRIVATE_TYPE_OBJECT && $fundTransactionDB->customer_code == null) {
                // Không cần làm gì cả
            }

            // Nếu là nội bộ và đã có thông tin khách hàng
            if ($type_object == FundTransaction::PRIVATE_TYPE_OBJECT && $fundTransactionDB->customer_code != null) {
                // - Khách hàng cũ bị trừ ví ( đc trừ âm)
                $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);
            }

            // Nếu là khách hàng và chưa có thông tin khách hàng
            if ($type_object == FundTransaction::CUSTOMER_TYPE_OBJECT && $fundTransactionDB->customer_code == null) {
                // - Khách hàng mới được nạp ví
                $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $params['customer_code']);
            }

            // Nếu là khách hàng và đã có thông tin khác hàng , nhập thay đổi khách hàng mới
            if ($type_object == FundTransaction::CUSTOMER_TYPE_OBJECT &&
                $fundTransactionDB->customer_code != null &&
                $fundTransactionDB->customer_code != $params['customer_code']) {

                // - Khách hàng cũ bị trừ ví ( đc trừ âm)
                $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

                // - Khách hàng mới được nạp ví
                $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $params['customer_code']);
            }

            // Cập nhật thông tin toàn bộ
            $this->updateWaitConfirmInfo($params, $fundTransactionDB);

            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function updateWaitConfirmInfo($param_update, $fundTransactionDB)
    {
        if (isset($param_update['customer_code']) && !is_null($param_update['customer_code'])) {
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $param_update['customer_code'])->first();

            $fundTransactionDB->customer_id    = (int) $customer->id;
            $fundTransactionDB->customer_code  = $customer->code;
            $fundTransactionDB->customer_name  = $customer->name;
            $fundTransactionDB->customer_phone = $customer->phone_number;
        }

        if (array_key_exists('customer_code', $param_update) && is_null($param_update['customer_code'])) {
            $fundTransactionDB->customer_id    = 0;
            $fundTransactionDB->customer_code  = null;
            $fundTransactionDB->customer_name  = null;
            $fundTransactionDB->customer_phone = null;
        }

        if (isset($param_update['note'])) {
            $fundTransactionDB->note = $param_update['note'];
        }

        if (isset($param_update['type_object'])) {
            $fundTransactionDB->type_object = $param_update['type_object'];
        }

        if (isset($param_update['fund_type_pay_id'])) {
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypePayDB = $fundTypePayModel->where('id', $param_update['fund_type_pay_id'])->first();

            $fundTransactionDB->fund_type_pay_id = $param_update['fund_type_pay_id'];
            $fundTransactionDB->fund_type_pay_code = $fundTypePayDB->code;
        }

        $fundTransactionDB->save();
    }

    public function updateDeposit(Request $request)
    {
        $validationRules = [
            'fund_transaction_id' => 'required|exists:fund_transactions,id',
            'customer_code'       => 'required|exists:customers,code',
            'fund_type_pay_id'    => 'required|exists:fund_type_pays,id',
            'money_update'        => 'required|numeric|min:1',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransactionDB = $fundTransactionModel->where('id', $params['fund_transaction_id'])
                                            ->where('fund_type_pay_id', 1)
                                            ->where('lock', 0)->first();
        if (is_null($fundTransactionDB)) {
            return resError('Bản ghi không tồn tại');
        }

        $fundTypePayModel = \App::make('App\Models\FundTypePay');
        $customerModel = \App::make('App\Models\Customer');
        $fundTypePayDB = $fundTypePayModel->where('id', $params['fund_type_pay_id'])->first();

        try {
            \DB::beginTransaction();

            $customer_code    = $params['customer_code'];
            $fund_type_pay_id = $params['fund_type_pay_id'];
            $money_update     = $params['money_update'];

            // Ghi log
            $params = $request->only([
                'customer_code',
                'fund_type_pay_id',
                'money_update',
            ]);
            $this->writeLogFundTransactionGroup($params, $fundTransactionDB);

            // Xử lý 8 trường hợp
            // Trường hợp 1: Không update gì cả
            $this->updateDepositCase1($params, $fundTransactionDB);

            // Trường hợp 2: Cập nhật khách hàng
            $this->updateDepositCase2($params, $fundTransactionDB);

            // Trường hợp 3: Cập nhật khách hàng + số tiền
            $this->updateDepositCase3($params, $fundTransactionDB);

            // Trường hợp 4: Cập nhật khách hàng + số tiền + loại thu chi
            $this->updateDepositCase4($params, $fundTransactionDB);

            // Trường hợp 5: Cập nhật số tiền
            $this->updateDepositCase5($params, $fundTransactionDB);

            // Trường hợp 6: Cập nhật số tiền + loại thu chi
            $this->updateDepositCase6($params, $fundTransactionDB);

            // Trường hợp 7: Cập nhật loại thu chi
            $this->updateDepositCase7($params, $fundTransactionDB);

            // Trường hợp 8: Cập nhật loại thu chi + khách hàng
            $this->updateDepositCase8($params, $fundTransactionDB);

            // dd('Done');
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function getParamCompareUpdateDeposit($params, $fundTransactionDB)
    {
        $customer_code_old    = strtolower($fundTransactionDB->customer_code);
        $fund_type_pay_id_old = $fundTransactionDB->fund_type_pay_id;
        $money_update_old     = $fundTransactionDB->money_update;

        $customer_code_new    = strtolower($params['customer_code']);
        $fund_type_pay_id_new = $params['fund_type_pay_id'];
        $money_update_new     = $params['money_update'];

        return [
            'customer_code_old'    => $customer_code_old,
            'fund_type_pay_id_old' => $fund_type_pay_id_old,
            'money_update_old'     => $money_update_old,

            'customer_code_new'    => $customer_code_new,
            'fund_type_pay_id_new' => $fund_type_pay_id_new,
            'money_update_new'     => $money_update_new,
        ];
    }

    public function updateDepositInfo($param_update, $fundTransactionDB)
    {
        if (isset($param_update['customer_code'])) {
            // dump('Cập nhật customer_code');
            $customerModel = \App::make('App\Models\Customer');
            $customer = $customerModel->where('code', $param_update['customer_code'])->first();

            $fundTransactionDB->customer_id    = (int) $customer->id;
            $fundTransactionDB->customer_code  = $customer->code;
            $fundTransactionDB->customer_name  = $customer->name;
            $fundTransactionDB->customer_phone = $customer->phone_number;
        }

        if (isset($param_update['money_update'])) {
            // dump('Cập nhật money_update');
            $fundTransactionDB->money_update = $param_update['money_update'];
            $fundTransactionDB->money = $param_update['money_update'];
        }

        if (isset($param_update['fund_type_pay_id'])) {
            // dump('Cập nhật fund_type_pay_id');
            $fundTypePayModel = \App::make('App\Models\FundTypePay');
            $fundTypePayDB = $fundTypePayModel->where('id', $param_update['fund_type_pay_id'])->first();

            $fundTransactionDB->fund_type_pay_id = $param_update['fund_type_pay_id'];
            $fundTransactionDB->fund_type_pay_code = $fundTypePayDB->code;
        }

        $fundTransactionDB->save();
    }

    // Trừ tiền khách cũ
    public function decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB)
    {
        // dump('Trừ tiền khách cũ');
        // Trừ tiền ở ví khách cũ
        $amount = $fundTransactionDB->money_update;
        $content = "Hủy " . $fundTransactionDB->note;
        $content = "Hủy nạp tiền ví điện tử !";
        $customer_id = (int) $fundTransactionDB->customer_id;
        $order = null;

        $isIncrement = false;
        $isBalanceNegative = true;

        (new \App\Services\TransactionService())->setTransaction(
            $amount,
            \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
            $content,
            $isIncrement,
            $customer_id,
            $fundTransactionDB,
            $isBalanceNegative,
            $order
        );
        ReportCustomer::query()->where('customer_id', $customer_id)->decrement('deposited_amount', $amount);
    }

    // Cộng tiền tiền khách mới
    public function increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $customer_code)
    {
        // dump('Cộng tiền khách mới');
        // Cộng tiền vào ví khách mới
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('code', $customer_code)->first();

        $amount = $fundTransactionDB->money_update;
        $content = $fundTransactionDB->note;
        $content = 'Chuyển tiền mua hàng !';
        $customer_id = (int) $customer->id;
        $soure = $fundTransactionDB;

        (new \App\Services\TransactionService())->setTransactionIncrement(
            $amount,
            \App\Constants\TransactionConstant::STATUS_DEPOSIT,
            $content,
            $customer_id,
            $soure
        );
        ReportCustomer::query()->where('customer_id', $customer_id)->increment('deposited_amount', $amount);
    }

    // Cộng or trừ tiền chênh lệch cho khách cũ
    public function deOrIncreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB, $money_update_new)
    {
        $moneyDiff = $money_update_new - $fundTransactionDB->money_update;
        // Số tiền mới nhỏ hơn => Trừ tiền của khách
        if ($moneyDiff < 0) {
            // dump('Trừ tiền của khách cũ nội bộ');
            $amount = abs($moneyDiff);
            $content = "Hủy nạp tiền ví điện tử !!";
            $customer_id = (int) $fundTransactionDB->customer_id;
            $order = null;

            $isIncrement = false;
            $isBalanceNegative = true;

            (new \App\Services\TransactionService())->setTransaction(
                $amount,
                \App\Constants\TransactionConstant::STATUS_WITHDRAWAL,
                $content,
                $isIncrement,
                $customer_id,
                $fundTransactionDB,
                $isBalanceNegative,
                $order
            );
            ReportCustomer::query()->where('customer_id', $customer_id)->decrement('deposited_amount', $amount);
        }

        if ($moneyDiff > 0) {
            // dump('Nạp thêm tiền cho khách nội bộ');
            // Cộng tiền vào ví khách mới
            $amount = abs($moneyDiff);
            $content = $fundTransactionDB->note;
            $content = 'Chuyển tiền mua hàng !';
            $customer_id = (int) $fundTransactionDB->customer_id;
            $soure = $fundTransactionDB;

            (new \App\Services\TransactionService())->setTransactionIncrement(
                $amount,
                \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                $content,
                $customer_id,
                $soure
            );
            ReportCustomer::query()->where('customer_id', $customer_id)->increment('deposited_amount', $amount);
        }
    }

    public function updateDepositCase1($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    == $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] == $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     == $data_compare['money_update_new']
        ) {
            // dump('Case 1');
        }
    }

    public function updateDepositCase2($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    != $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] == $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     == $data_compare['money_update_new']
        ) {
            // dump('Case 2');

            // - Khách hàng cũ bị trừ ví ( đc trừ âm)
            $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

            // - Khách hàng mới được nạp ví
            $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $data_compare['customer_code_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
               'customer_code' =>  $data_compare['customer_code_new']
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase3($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    != $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] == $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     != $data_compare['money_update_new']
        ) {
            // dump('Case 3');

            // - Khách hàng cũ bị trừ ví ( đc trừ âm)
            $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

            // - Khách hàng mới được nạp ví (Cộng theo số tiền mới thôi)
            $fundTransactionDB->money_update = $data_compare['money_update_new'];
            $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $data_compare['customer_code_new']);

            // - Cộng bù trừ tiền chênh lệch ở quỹ (Cần cho lại về giá trị cũ)
            $fundTransactionDB->money_update = $data_compare['money_update_old'];
            $this->updateBalanceWhenUpdate($fundTransactionDB, $data_compare['money_update_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
               'customer_code' =>  $data_compare['customer_code_new'],
               'money_update'  =>  $data_compare['money_update_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase4($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        // - Phiếu thu update thông tin khách hàng, loại thanh toán và số tiền
        // - Khách hàng mới được nạp ví số tiền mới
        // - Khách hàng cũ bị trừ ví số tiền cũ (có thể âm ví)
        // - Quỹ được cộng thêm số tiền chênh lệch = Số tiền mới - Số tiền cũ ( có thể ấm ví

        if (
            $data_compare['customer_code_old']    != $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] != $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     != $data_compare['money_update_new']
        ) {
            // dump('Case 4');

            // - Khách hàng cũ bị trừ ví ( đc trừ âm)
            $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

            // - Khách hàng mới được nạp ví (Cộng theo số tiền mới thôi)
            $fundTransactionDB->money_update = $data_compare['money_update_new'];
            $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $data_compare['customer_code_new']);

            // - Cộng bù trừ tiền chênh lệch ở quỹ (Cần cho lại về giá trị cũ)
            $fundTransactionDB->money_update = $data_compare['money_update_old'];
            $this->updateBalanceWhenUpdate($fundTransactionDB, $data_compare['money_update_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
               'customer_code'    =>  $data_compare['customer_code_new'],
               'money_update'     =>  $data_compare['money_update_new'],
               'fund_type_pay_id' =>  $data_compare['fund_type_pay_id_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase5($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    == $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] == $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     != $data_compare['money_update_new']
        ) {
            // dump('Case 5');

            // Khách hàng được nạp ví số tiền chênh lệch ( được trừ âm)
            $this->deOrIncreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB, $data_compare['money_update_new']);

            // - Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenUpdate($fundTransactionDB, $data_compare['money_update_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
               'money_update'     =>  $data_compare['money_update_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase6($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    == $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] != $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     != $data_compare['money_update_new']
        ) {
            // dump('Case 6');

            // Khách hàng được nạp ví số tiền chênh lệch ( được trừ âm)
            $this->deOrIncreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB, $data_compare['money_update_new']);

            // - Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenUpdate($fundTransactionDB, $data_compare['money_update_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
               'money_update'     =>  $data_compare['money_update_new'],
               'fund_type_pay_id' =>  $data_compare['fund_type_pay_id_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase7($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    == $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] != $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     = $data_compare['money_update_new']
        ) {
            // dump('Case 7');

            // Cập nhật lại phiếu thu
            $param_update = [
               'fund_type_pay_id' =>  $data_compare['fund_type_pay_id_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    public function updateDepositCase8($params, $fundTransactionDB)
    {
        $data_compare = $this->getParamCompareUpdateDeposit($params, $fundTransactionDB);

        if (
            $data_compare['customer_code_old']    != $data_compare['customer_code_new'] &&
            $data_compare['fund_type_pay_id_old'] != $data_compare['fund_type_pay_id_new'] &&
            $data_compare['money_update_old']     = $data_compare['money_update_new']
        ) {
            // dump('Case 8');

            // - Khách hàng cũ bị trừ ví ( đc trừ âm)
            $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

            // - Khách hàng mới được nạp ví
            $this->increaseMoneyCustomerNewUpdateDeposit($fundTransactionDB, $data_compare['customer_code_new']);

            // Cập nhật lại phiếu thu
            $param_update = [
                'customer_code' =>  $data_compare['customer_code_new'],
                'fund_type_pay_id' =>  $data_compare['fund_type_pay_id_new'],
            ];
            $this->updateDepositInfo($param_update, $fundTransactionDB);
        }
    }

    // Danh sách giao dịch bị xóa bên VN
    public function paginationDelete(Request $request)
    {
        return $this->_service->paginationDelete(PaginateHelper::getPerPage());
    }

    public function paginationDeleteTQ(Request $request)
    {
        return $this->_service->paginationDeleteTQ(PaginateHelper::getPerPage());
    }

    public function delete2024(Request $request)
    {
        $validationRules = [
            'fund_transaction_id' => 'required|exists:fund_transactions,id',
        ];
        $validationMessages = [

        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();

        $fundTransactionModel = \App::make('App\Models\FundTransaction');
        $fundTransactionDB = $fundTransactionModel->where('id', $params['fund_transaction_id'])->where('lock', 0)->first();
        if (is_null($fundTransactionDB)) {
            return resError('Bản ghi không tồn tại');
        }

        try {
            \DB::beginTransaction();


            if ($fundTransactionDB->type_pay == 1) {
                // Trường hợp 1: Đối tượng chuyển quỹ, đơn vị là tiền VN
                $result = $this->delete2024TypePay1Case1($fundTransactionDB);

                // Trường hợp 2: Đối tượng nội bộ, nạp tiền TQ - Quỹ TQ nhận dc tiền
                $result = $this->delete2024TypePay1Case2($fundTransactionDB);

                // Trường hợp 3: Đối tượng nội bộ, không phải nạp tiền TQ (+ TH6: Khởi tạo quỹ)
                $result = $this->delete2024TypePay1Case3($fundTransactionDB);

                // Trường hợp 4: Đối tượng khách hàng và chưa được gán khách hàng (Tiền chờ xác nhận)
                $result = $this->delete2024TypePay1Case4($fundTransactionDB);

                // Trường hợp 5: Đối tượng khách hàng và đã được gán khách hàng
                $result = $this->delete2024TypePay1Case5($fundTransactionDB);
            }

            if ($fundTransactionDB->type_pay == 2) {
                // Trường hợp 1: Đối tượng chuyển quỹ, đơn vị là tiền VN
                $result = $this->delete2024TypePay2Case1($fundTransactionDB);

                // Trường hợp 2: Đối tượng nội bộ, nạp tiền TQ - Quỹ VN chi tiền
                $result = $this->delete2024TypePay2Case2($fundTransactionDB);

                // Trường hợp 3: Đối tượng nội bộ, không phải nạp tiền TQ
                $result = $this->delete2024TypePay2Case3($fundTransactionDB);

                // Trường hợp 4: Đối tượng khách hàng, Đổi tiền cho khách
                $result = $this->delete2024TypePay2Case4($fundTransactionDB);

                // Trường hợp 5: Đối tượng khách hàng, không phải là đổi tiền cho khách
                $result = $this->delete2024TypePay2Case5($fundTransactionDB);
            }

            if (env('SHOW_DUMP', false)) {
                dd("done");
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function delete2024DumpLog($log)
    {
        $showDump = env('SHOW_DUMP', false);
        if ($showDump) {
            dump('Log: ' . $log);
        } else {
            \Log::info('Log: ' . $log);
        }
    }

    public function updateLogAndDelete2024($fundTransactionDB)
    {
        // Thêm ghi log
        $userOnline = \Auth::user();
        $error_logsDB = $fundTransactionDB->logs == null ? [] : json_decode($fundTransactionDB->logs);

        $temp_log = [
            'action'             => 'Xóa',

            'user_request_name'  => $userOnline->name,
            'user_request_email' => $userOnline->email,
            'user_request_id'    => $userOnline->id,

            'column'             => "",
            'value_old'          => "",
            'value_new'          => "",

            'time'               => date('Y-m-d H:i:s', time())
        ];

        array_push($error_logsDB, $temp_log);

        $fundTransactionDB->logs = json_encode($error_logsDB, JSON_UNESCAPED_UNICODE);
        $fundTransactionDB->lock = 1;
        $fundTransactionDB->save();

        // Xóa nó
        $fundTransactionDB->delete();
    }

    private function updateBalanceWhenDelete($fundTransaction)
    {
        $fundTransactionAll = $this->getAllFundTransaction($fundTransaction);
        if(empty($fundTransactionAll)) {
            return false;
        }
        // type_pay = 1 là giao dịch + tiền;
        // type_pay = 2 là giao dịch - tiền;

        foreach($fundTransactionAll as $fund) {
            if($fundTransaction->type_pay == 1) {
                $balance = $fund->balance - $fundTransaction->money_update;
            }

            if($fundTransaction->type_pay == 2) {
                $balance = $fund->balance + $fundTransaction->money_update;
            }
            FundTransaction::where('id', $fund->id)->update(['balance' => $balance]);
        }

        // Cập nhật lại balance trong bảng funds
        if($fundTransaction->fund){
            // Nếu là thu tiền => giảm blance , giảm số tiền nhận thêm
            if($fundTransaction->type_pay == 1){
                $fundTransaction->fund()->decrement('total_money_in', $fundTransaction->money_update);
                $fundTransaction->fund()->decrement('current_balance', $fundTransaction->money_update);
            }
            // Nếu là chi tiền => tăng blance lại , giảm số tiền phải chi
            if($fundTransaction->type_pay == 2){
                $fundTransaction->fund()->decrement('total_money_out', $fundTransaction->money_update);
                $fundTransaction->fund()->increment('current_balance', $fundTransaction->money_update);
            }
        }
    }

    // delete2024TypePay1
    // delete2024TypePay1
    // delete2024TypePay1
    public function delete2024TypePay1Case1($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::FUND_TYPE_OBJECT &&
            $fundTransactionDB->fund_unit_currency == Fund::VND_UNIT_CURRENCY
        ) {
            $this->delete2024DumpLog('TypePay1Case1');

            // - Xóa phiếu thu và trừ tiền của quỹ nhận
            // - Xóa phiếu chi và cộng tiền vào quỹ chi
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            // Tìm bản ghi liên quan để xóa
            $fundTransactionRelation = $fundTransactionModel->where('id', $fundTransactionDB->fund_transaction_id)->first();
            if ($fundTransactionRelation) {
                // Xóa nó
                $this->updateLogAndDelete2024($fundTransactionRelation);
                // Cộng bù trừ tiền chênh lệch ở quỹ
                $this->updateBalanceWhenDelete($fundTransactionRelation);
            }

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay1Case2($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::PRIVATE_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code == 4
        ) {
            $this->delete2024DumpLog('TypePay1Case2');
            // Xóa phiếu thu và trừ tiền của quỹ ( đc trừ âm)
            // Update lại số dư mới của các giao dịch có sau giao dịch bị xóa
            // Xóa phiếu thu nội bộ VN

            // Tìm các bản ghi quỹ VN
            $fundTransactionModel = \App::make('App\Models\FundTransaction');
            $fundTransactionRelations = $fundTransactionModel->where('fund_transaction_id', $fundTransactionDB->id)->get();

            if (count($fundTransactionRelations)) {
                foreach ($fundTransactionRelations as $key => $item) {
                    // Xóa nó
                    $this->updateLogAndDelete2024($item);
                    // Cộng bù trừ tiền chênh lệch ở quỹ
                    $this->updateBalanceWhenDelete($item);
                }
            }

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay1Case3($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::PRIVATE_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code != 4
        ) {
            $this->delete2024DumpLog('TypePay1Case3');
            // - Xóa phiếu thu và trừ tiền của quỹ ( đc trừ âm)
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay1Case4($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::CUSTOMER_TYPE_OBJECT &&
            $fundTransactionDB->customer_code == null
        ) {
            $this->delete2024DumpLog('TypePay1Case4');

            // - Xóa phiếu thu và trừ tiền Quỹ ( đc trừ âm)
            // -  Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay1Case5($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::CUSTOMER_TYPE_OBJECT &&
            $fundTransactionDB->customer_code != null
        ) {
            $this->delete2024DumpLog('TypePay1Case5');

            // - Xóa phiếu thu và trừ tiền Quỹ (đc trừ âm )
            // - Trừ ví khách ( đc trừ âm)
            // - - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // - Khách hàng cũ bị trừ ví ( đc trừ âm)
            // $this->decreaseMoneyCustomerOldUpdateDeposit($fundTransactionDB);

            // Logic 18/11/2024: Bên khách hàng xóa giao dịch, cập nhật lại số dư chứ không tạo bản ghi đối ứng nữa như hàm decreaseMoneyCustomerOldUpdateDeposit
            $this->deleteTransationAndUpdateBaladeCustomer($fundTransactionDB);

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }


    // delete2024TypePay2
    // delete2024TypePay2
    // delete2024TypePay2
    public function delete2024TypePay2Case1($fundTransactionDB)
    {
        $this->delete2024TypePay1Case1($fundTransactionDB);
    }

    public function delete2024TypePay2Case2($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::PRIVATE_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code == 4
        ) {
            $this->delete2024DumpLog('TypePay2Case2');
            // - Xóa toàn bộ phiếu chi của các ngân hàng VN và cộng tiền vào các quý
            // - Xóa phiếu thu của ngân hàng TQ là trừ số tiền trong các lô đã nạp
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Tìm các bản ghi ở VN
            $fundTransactionModel = \App::make('App\Models\FundTransaction');
            $fundTransactionRelations = $fundTransactionModel
                                ->where('fund_transaction_id', $fundTransactionDB->fund_transaction_id)
                                ->whereNotIn('id', [$fundTransactionDB->id])
                                ->get();

            if (count($fundTransactionRelations)) {
                foreach ($fundTransactionRelations as $key => $item) {
                    // Xóa nó
                    $this->updateLogAndDelete2024($item);
                    // Cộng bù trừ tiền chênh lệch ở quỹ
                    $this->updateBalanceWhenDelete($item);
                }
            }
            // Tìm bản ghi CN
            $fundTransactionRelation = $fundTransactionModel->where('id', $fundTransactionDB->fund_transaction_id)->first();
            if ($fundTransactionRelation) {
                // Xóa nó
                $this->updateLogAndDelete2024($fundTransactionRelation);
                // Cộng bù trừ tiền chênh lệch ở quỹ
                $this->updateBalanceWhenDelete($fundTransactionRelation);
            }

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay2Case3($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::PRIVATE_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code != 4
        ) {
            $this->delete2024DumpLog('TypePay2Case3');

            // - Xóa phiếu chi và cộng tiền kèm tỉ giá tương ứng vào quỹ tệ
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function delete2024TypePay2Case4($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::CUSTOMER_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code == 2
        ) {
            $this->delete2024DumpLog('TypePay2Case4');

            // - Xóa phiếu chi và cộng tiền kèm tỉ giá tương ứng vào quỹ tệ
            // - Cộng tiền lại vào ví cho khách
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Trả tiền cho khách
            // $this->backMoneyDelete2024TypePay2Case4($fundTransactionDB);

            // Logic 18/11/2024: Bên khách hàng xóa giao dịch, cập nhật lại số dư chứ không tạo bản ghi đối ứng nữa như hàm backMoneyDelete2024TypePay2Case4
            $this->deleteTransationAndUpdateBaladeCustomer($fundTransactionDB);

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    public function backMoneyDelete2024TypePay2Case4($fundTransactionDB)
    {
        $customerModel = \App::make('App\Models\Customer');
        $customer = $customerModel->where('id', $fundTransactionDB->customer_id)->first();

        // Trả tiền lại lại trả khách
        $amount = ($fundTransactionDB->cn_change + $fundTransactionDB->fee_customer) * $fundTransactionDB->fee_customer_ratio;
        $content = 'Hoàn tiền Đổi tiền Việt sang tiền Trung';
        $customer_id = (int)$customer->id;
        $soure = $fundTransactionDB;

        (new \App\Services\TransactionService())->setTransactionIncrement(
            $amount,
            \App\Constants\TransactionConstant::STATUS_REFUND,
            $content,
            $customer_id,
            $soure
        );
    }

    public function delete2024TypePay2Case5($fundTransactionDB)
    {
        if (
            $fundTransactionDB->type_object == FundTransaction::CUSTOMER_TYPE_OBJECT &&
            $fundTransactionDB->fund_type_pay_code != 2
        ) {
            $this->delete2024DumpLog('TypePay2Case5');

            //  Xóa phiếu chi và cộng tiền vào quỹ
            // - Cộng tiền vào ví khách
            // - Update lại số dư mới của các giao dịch có sau giao dịch bị xóa

            // Trả tiền cho khách
            // $this->increaseMoneyCustomerOldDelete2024($fundTransactionDB);

            // Logic 18/11/2024: Bên khách hàng xóa giao dịch, cập nhật lại số dư chứ không tạo bản ghi đối ứng nữa như hàm increaseMoneyCustomerOldDelete2024
            $this->deleteTransationAndUpdateBaladeCustomer($fundTransactionDB);

            // Xóa nó
            $this->updateLogAndDelete2024($fundTransactionDB);
            // Cộng bù trừ tiền chênh lệch ở quỹ
            $this->updateBalanceWhenDelete($fundTransactionDB);
        }
    }

    // Cộng tiền tiền khách cũ
    public function increaseMoneyCustomerOldDelete2024($fundTransactionDB)
    {
        $this->delete2024DumpLog('Trả lại tiền khách cũ');
        $amount = $fundTransactionDB->money_update;
        $content = 'Hủy ' . $fundTransactionDB->note;
        $customer_id = (int) $fundTransactionDB->customer_id;
        $soure = $fundTransactionDB;

        (new \App\Services\TransactionService())->setTransactionIncrement(
            $amount,
            \App\Constants\TransactionConstant::STATUS_REFUND,
            $content,
            $customer_id,
            $soure
        );

        ReportCustomer::query()->where('customer_id', $customer_id)->decrement('withdrawal_amount', $amount);
    }

    public function storeArr(Request $request)
    {
        $validationRules = [
            'type_object'                   => 'required|in:2,3',
            'type_pay'                      => 'required|in:1,2',
            'fund_type_pay_id'              => 'required|exists:fund_type_pays,id',
            'fund_id'                       => 'required|exists:funds,id',

            'arr_customer'                  => 'nullable|array',
            'arr_customer.*.customer_code'  => 'required|exists:customers,code',
            'arr_customer.*.customer_money' => 'required|numeric|min:1|max:10000000000',

            'money'                         => 'nullable|numeric|min:0|max:10000000000',
            'note'                          => 'required|string|max:1000',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);
        $params = $request->all();

        // Logic xử lý là dựa vào hàm Store đơn đã đúng,
        // Ở đây chỉ làm động tác loop để inset nhiều khách 1 lúc

        $result_check = $this->handleBeforeStoreArr($params);

        if(!is_array($result_check)) {
            $result = [
                'error' => $result_check
            ];
            return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
        }

        try {
            \DB::beginTransaction();
            if (count($result_check) > 0) {
                foreach ($result_check as $key => $data_inset) {
                    $this->storeArrAction($data_inset);
                }
            }
            \DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            \DB::rollback();
            throw $e;
        }
    }

    public function handleBeforeStoreArr($params)
    {
        $arr_result = [];
        // Nếu đổi tượng là khách hàng thì bắt nhập arr_customer
        if ($params['type_object'] == FundTransaction::CUSTOMER_TYPE_OBJECT) {
            if (!isset($params['arr_customer'])) {
                return 'Chưa nhập mảng khách hàng';
            }
            if (count($params['arr_customer']) == 0) {
                return 'Nhập tối thiểu 1 khách hàng';
            }
            if (count($params['arr_customer']) > 20) {
                return 'Tối đã nhận 20 khách hàng';
            }

            $arr_code = [];
            foreach ($params['arr_customer'] as $key => $package) {
                array_push($arr_code, $package['customer_code']);
            }
            $arr_code_unique = array_unique($arr_code);
            if (count($arr_code_unique) != count($arr_code)) {
                return 'Có mã khách hàng bị nhập 2 lần';
            }
        }

        $textError = "";
        $dataError = null;

        // Check loop theo hàm cũ
        foreach ($params['arr_customer'] as $key => $customer) {
            $data_check = [
                'type_object'      => $params['type_object'],
                'type_pay'         => $params['type_pay'],
                'fund_type_pay_id' => $params['fund_type_pay_id'],
                'fund_id'          => $params['fund_id'],
                'customer_code'    => $customer['customer_code'],
                'money'            => $customer['customer_money'],
                'note'             => $params['note'],
            ];

            $data = $this->handleBeforeStore($data_check);

            if(!is_array($data)) {
                $textError .= $customer['customer_code'] . ",";
                $dataError = $data;

                // Gốc lúc đầu
                // $text = reset($data->original['data']);
                // return 'Khách hàng ' . $customer['customer_code'] . ' : ' . $text;
            }

            array_push($arr_result, $data);
        }

        if ($textError != "") {
            $text = reset($dataError->original['data']);
            $textError = rtrim($textError, ",");
            return 'Khách hàng ' . $textError . ' : ' . $text;
        }

        return $arr_result;
    }

    public function storeArrAction($data)
    {
        $customer = $data['customer'];
        $result = $this->_service->store($data);

        if(data_get($result, 'type_object') == FundTransaction::CUSTOMER_TYPE_OBJECT){
            // Giao dich thu
            if(data_get($result, 'type_pay') == FundTransaction::REVENUE_TYPE_PAY){
                $amount = (int) $result->money;
                $content = $data['fund_type_name'];
                $content = $data['note']; // Logic ngày 11/10/2024
                $customer_id = $customer->id;
                $soure = $result;

                (new \App\Services\TransactionService())->setTransactionIncrement(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_DEPOSIT,
                    $content,
                    $customer_id,
                    $soure
                );
                $contentNoti = 'Chuyển tiền mua hàng thành công ' . number_format($amount, 0, ',', '.') . ' đ';
                $this->updateNotiLogic($customer, $data, $contentNoti);
                // Cộng vào số tiền khách đã nạp
                ReportCustomer::query()->where('customer_id', $customer_id)->increment('deposited_amount', $amount);
            }
            // Giao dich chi
            if(data_get($result, 'type_pay') == FundTransaction::EXPENSE_TYPE_PAY){
                // Trừ tiền của khách
                $amount = (int) $result->money;
                $content = $data['fund_type_name'];
                $content = $data['note']; // Logic ngày 11/10/2024
                $customer_id = $customer->id;
                $soure = $result;

                // (new \App\Services\TransactionService())->setTransactionDecrement(
                //     $amount,
                //     \App\Constants\TransactionConstant::STATUS_PURCHASE,
                //     $content,
                //     $customer_id,
                //     $soure
                // );

                // Cho phép trừ âm luôn (Trường hợp nào ko dc trừ âm thì đã chặn ở Validate rồi)
                $isIncrement = false;
                $isBalanceNegative = true;
                $order = null;

                (new \App\Services\TransactionService())->setTransaction(
                    $amount,
                    \App\Constants\TransactionConstant::STATUS_PURCHASE,
                    $content,
                    $isIncrement,
                    $customer_id,
                    $soure,
                    $isBalanceNegative,
                    $order
                );

                ReportCustomer::query()->where('customer_id', $customer_id)->increment('purchase_amount', $amount);
                // Cộng vào số tiền khách rút
                // ReportCustomer::query()->where('customer_id', $customer_id)->increment('withdrawal_amount', $amount);
            }
        }
    }

    public function listMoneyChange(Request $request)
    {
        $validationRules = [
            'customer_code'  => 'nullable|exists:customers,code',
            'customer_phone' => 'nullable|exists:customers,phone_number',
            'fund_id'        => 'nullable|exists:funds,id',
            'date_from'      => 'nullable|date_format:Y-m-d',
            'date_to'        => 'nullable|date_format:Y-m-d',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        $per_page = request()->get('per_page', 25);

        $customer_code  = request()->customer_code;
        $customer_phone = request()->customer_phone;
        $fund_id        = request()->fund_id;
        $date_from      = request()->date_from;
        $date_to        = request()->date_to;

        try {
            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            $model  = $fundTransactionModel->where('fund_type_pay_code', 2);

            if (!is_null($customer_code)) {
                $model = $model->where('customer_code', $customer_code);
            }
            if (!is_null($customer_phone)) {
                $model = $model->where('customer_phone', $customer_phone);
            }
            if (!is_null($fund_id)) {
                $model = $model->where('fund_id', $fund_id);
            }
            if (!is_null($date_from)) {
                $date_from = $date_from . ' 00:00:00';
                $model = $model->where('created_at', '>=',  $date_from);
            }
            if (!is_null($date_to)) {
                $date_to = $date_to . ' 23:59:59';
                $model = $model->where('created_at', '<=', $date_to);
            }

            $model = $model->orderBy('id', 'desc');
            $users = $model->paginate($per_page);

            $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
            $_resource = \App\Http\Resources\FundTransaction\FundTransactionRmbResource::class;
            return resSuccessWithinData(new $_paginateResource($users, $_resource));
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function sumMoneyChange(Request $request)
    {
        $validationRules = [
            'customer_code'  => 'nullable|exists:customers,code',
            'customer_phone' => 'nullable|exists:customers,phone_number',
            'fund_id'        => 'nullable|exists:funds,id',
            'date_from'      => 'nullable|date_format:Y-m-d',
            'date_to'        => 'nullable|date_format:Y-m-d',
        ];
        $validationMessages = [
        ];

        $validated = $request->validate($validationRules, $validationMessages);

        $params = $request->all();
        $per_page = request()->get('per_page', 25);

        $customer_code  = request()->customer_code;
        $customer_phone = request()->customer_phone;
        $fund_id        = request()->fund_id;
        $date_from      = request()->date_from;
        $date_to        = request()->date_to;

        try {
            $fundTransactionModel = \App::make('App\Models\FundTransaction');

            $model  = $fundTransactionModel->where('fund_type_pay_code', 2);

            if (!is_null($customer_code)) {
                $model = $model->where('customer_code', $customer_code);
            }
            if (!is_null($customer_phone)) {
                $model = $model->where('customer_phone', $customer_phone);
            }
            if (!is_null($fund_id)) {
                $model = $model->where('fund_id', $fund_id);
            }
            if (!is_null($date_from)) {
                $date_from = $date_from . ' 00:00:00';
                $model = $model->where('created_at', '>=',  $date_from);
            }
            if (!is_null($date_to)) {
                $date_to = $date_to . ' 23:59:59';
                $model = $model->where('created_at', '<=', $date_to);
            }

            $select = '
                    count(*) as total_pack,
                    sum(
                        ((money_update - fee_system + fee_customer) * fee_customer_ratio) -
                        ((money_update - fee_system + fee_system) * fee_system_ratio)
                    ) as profit
                    ';

            $model = $model->selectRaw($select)->first();

            $arr_result = [
                'profit' => 0
            ];
            if ($model) {
                $arr_result['profit'] = $model['profit'] == null ? 0 : $model['profit'];
            }

            return resSuccessWithinData($arr_result);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function deleteTransationAndUpdateBaladeCustomer($fundTransactionDB)
    {
        $transactionModel = \App::make('App\Models\Transaction');
        $reportCustomerModel = \App::make('App\Models\ReportCustomer');

        $transactionDB = $transactionModel->where('sourceable_type', 'App\Models\FundTransaction')
                                          ->where('sourceable_id', $fundTransactionDB->id)
                                          ->where('customer_id', $fundTransactionDB->customer_id)
                                          ->first();

        $customer_id  = $transactionDB->customer_id; // Khách hàng nào
        $money_change = $transactionDB->amount; // Số tiền thay đổi

        if (!is_null($transactionDB)) {
            // Update số dư các bản ghi phía trên của transactionDB
            $allTransactionOfCustomerBefore = $transactionModel->where('customer_id', $customer_id)
                                                               ->where('id', '>', $transactionDB->id)
                                                               ->get();
            if (count($allTransactionOfCustomerBefore) > 0) {
                foreach ($allTransactionOfCustomerBefore as $key => $item) {
                    // Nếu là giao dịch thu tiền
                    if ($fundTransactionDB->type_pay == 1) {
                        $item->balance -= $money_change;
                        $item->save();
                    }
                    // Nếu là giao dịch chi tiền
                    if ($fundTransactionDB->type_pay == 2) {
                        $item->balance += $money_change;
                        $item->save();
                    }
                }
            }

            // Cập nhật lại balance của khách
            $reportCustomerDB = $reportCustomerModel->where('customer_id', $customer_id)->first();
            if (!is_null($reportCustomerDB)) {
                // Nếu là giao dịch thu tiền
                if ($fundTransactionDB->type_pay == 1) {
                    $reportCustomerDB->balance_amount -= $money_change;
                    $reportCustomerDB->deposited_amount -= $money_change;
                    $reportCustomerDB->save();
                }
                // Nếu là giao dịch chi tiền
                if ($fundTransactionDB->type_pay == 2) {
                    $reportCustomerDB->balance_amount += $money_change;
                    $reportCustomerDB->purchase_amount -= $money_change;
                    $reportCustomerDB->save();
                }
            }

            // Xóa transactionDB của khách hàng
            $transactionDB->delete();
        }
    }

    private function updateNotiForClient($customerId, $content)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_customer = [
            'content' => $content
        ];

        $data = [
            'group'       => 8,
            'name'        => 8001,
            'data'        => json_encode($data_customer),
            'time_create' => time(),
            'customer_id' => $customerId,
            'status'      => 1
        ];

        $notificationService->store($data);
    }
}
