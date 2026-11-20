<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ApproveUnitPrice;
use App\Constants\ApproveUnitPriceConstant;
use App\Services\ApproveUnitPriceService;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client as Guzzle;

class ApproveUnitPriceControler extends Controller
{
    public function __construct(ApproveUnitPriceService $service)
    {
        $this->_service = $service;
    }

    public function pagination(): JsonResponse
    {
        $per_page = request()->get('per_page', 25);
        $query = ApproveUnitPrice::query();
        if(!is_null(request()->input('status'))) {
            $query->where('status', request()->input('status'));
        }
        if(!is_null(request()->input('customer_phone'))) {
            $query->whereHas('customer', function($q) {
                $q->where('phone_number', request()->input('customer_phone'));
            });
        }
        if(!is_null(request()->input('bill_code'))) {
            $query->whereHas('orderPackage', function($q) {
                $q->where('bill_code', request()->input('bill_code'));
            });
        }
        $data = $query->with([
            'user',
            'customer',
            'orderPackage' => function ($query) {
                $query->select('id', 'bill_code');
            },
            'approveUser'
        ])->orderBy('id', 'desc')->paginate($per_page);

        $_paginateResource = \App\Http\Resources\PaginateJsonResource::class;
        $_resource = \App\Http\Resources\ApproveUnitPriceResource::class;
        return resSuccessWithinData(new $_paginateResource($data, $_resource));
    }

    public function countStatusPending()
    {
        $countHangVe = 0;
        $countQuote = 0;
        $auth = getCurrentUser();
        if($auth->hasPermissionName('pack.approve-unit-price-hangve') == true) {
            $countHangVe = ApproveUnitPrice::query()->where('status', ApproveUnitPriceConstant::PENDING)->count();
        }
        if($auth->hasPermissionName('pack.approve-unit-price-quote') == true) {
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/count-approve-pending';
            $options = [
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            $countQuote = data_get($result, 'data') ?? 0;
        }
        $totalCount = $countHangVe + $countQuote;
        return resSuccessWithinData($totalCount);
    }

    public function countStatus()
    {
        $data = ApproveUnitPrice::query()->selectRaw("count(id) as count, status")->groupBy('status')->get();
        $statusCount = [
            [
                'value'    => ApproveUnitPriceConstant::PENDING,
                'quantity' => 0,
                'name'     => ApproveUnitPriceConstant::STATUSES[ApproveUnitPriceConstant::PENDING],
                'color'    => ApproveUnitPriceConstant::STATUSES_COLOR[ApproveUnitPriceConstant::PENDING],
            ],
            [
                'value'    => ApproveUnitPriceConstant::APPROVED,
                'quantity' => 0,
                'name'     => ApproveUnitPriceConstant::STATUSES[ApproveUnitPriceConstant::APPROVED],
                'color'    => ApproveUnitPriceConstant::STATUSES_COLOR[ApproveUnitPriceConstant::APPROVED],
            ],
            [
                'value'    => ApproveUnitPriceConstant::CANCEL,
                'quantity' => 0,
                'name'     => ApproveUnitPriceConstant::STATUSES[ApproveUnitPriceConstant::CANCEL],
                'color'    => ApproveUnitPriceConstant::STATUSES_COLOR[ApproveUnitPriceConstant::CANCEL],
            ],
        ];
        $statusCount = array_map(function($item) use ($data) {
            $filtereStatus = $data->filter(function ($value) use ($item) {
                return $value->status == $item['value'];
            })->first();
            $item['quantity'] = $filtereStatus ? $filtereStatus->count : $item['quantity'];
            return $item;
        }, $statusCount);
        $listStatus = [];
        foreach ($statusCount as $key => $item) {
            array_push($listStatus, [
                'value' => $item['value'],
                'label' => $item['name'],
            ]);
        }
        return resSuccessWithinData([
            'status_count' => $statusCount,
            'list_status'  => $listStatus,
        ]);
    }

    public function approve(Request $request)
    {
        $validationRules = [
            'list_id' => 'required|array',
            'list_id.*' => 'required|exists:approve_unit_prices,id',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $listId = $request->input('list_id');
            $checkStatus = ApproveUnitPrice::query()->whereIn('id', $listId)->where('status', '!=', ApproveUnitPriceConstant::PENDING)->count();
            if($checkStatus > 0) {
                $result = [
                    'error' => "Trạng thái không hợp lệ"
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $this->_service->approve($listId);
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function cancel(Request $request)
    {
        $validationRules = [
            'list_id' => 'required|array',
            'list_id.*' => 'required|exists:approve_unit_prices,id',
            'cancel_reason' => 'required|string',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        try {
            DB::beginTransaction();
            $listId = $request->input('list_id');
            $checkStatus = ApproveUnitPrice::query()->whereIn('id', $listId)->where('status', '!=', ApproveUnitPriceConstant::PENDING)->count();
            if($checkStatus > 0) {
                $result = [
                    'error' => "Trạng thái không hợp lệ"
                ];
                return resErrorWithinData($result, 'Có lỗi xảy ra', 422);
            }
            $this->_service->cancel($listId, $request->input('cancel_reason'));
            DB::commit();
            return resSuccess();
        } catch (Exception $e) {
            DB::rollback();
            return resError($e->getMessage());
        }
    }

    public function listConfirmQuote(Request $request)
    {
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'quote/hangve/confirm-quote';
        $params = [
            'query' => [
                'bill_code' => request()->input('bill_code'),
                'customer_phone' => request()->input('customer_phone'),
                'quote_code' => request()->input('quote_code'),
                'status' => request()->input('status'),
                'page' => request()->input('page'),
                'per_page' => request()->input('per_page')
            ]
        ];
        $result = $guzzle->request('GET', $url, $params)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }

    public function approveQuote(Request $request)
    {
        $validationRules = [
            'confirm_quote_id' => 'required',
            'unit_price_kg' => 'required|numeric|min:0',
            'unit_price_m3' => 'required|numeric|min:0'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('confirm_quote_id', 'unit_price_kg', 'unit_price_m3');
        try {
            $currenUser = getCurrentUser();
            $data = array_merge($params, [
                'approve_user_id' => $currenUser->id,
                'approve_user_name' => $currenUser->name
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/approve-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function approveMultiQuote(Request $request)
    {
        $validationRules = [
            'confirm_quote_ids' => 'required'
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('confirm_quote_ids');
        try {
            $currenUser = getCurrentUser();
            $data = array_merge($params, [
                'approve_user_id' => $currenUser->id,
                'approve_user_name' => $currenUser->name
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/approve-multi-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function cancelQuote(Request $request)
    {
        $validationRules = [
            'list_id' => 'required|array',
            'list_id.*' => 'required',
            'cancel_reason' => 'required|string',
        ];
        $validationMessages = [];
        $validated = $request->validate($validationRules, $validationMessages);
        $params = request()->only('list_id', 'cancel_reason');
        try {
            $currenUser = getCurrentUser();
            $data = array_merge($params, [
                'approve_user_id' => $currenUser->id,
                'approve_user_name' => $currenUser->name
            ]);
            $guzzle = new Guzzle;
            $url = env('URL_QUOTE') . 'quote/hangve/cancel-quote';
            $options = [
                'json' => $data,
                'verify' => false,
                'http_errors' => true,
            ];
            $result = $guzzle->request('POST', $url, $options)->getBody()->getContents();
            $result = json_decode($result, true);
            return resSuccess();
        } catch (Exception $e) {
            return resError($e->getMessage());
        }
    }

    public function quoteCountStatus()
    {
        $guzzle = new Guzzle;
        $url = env('URL_QUOTE') . 'quote/hangve/count-status';
        $options = [
            'verify' => false,
            'http_errors' => true,
        ];
        $result = $guzzle->request('GET', $url, $options)->getBody()->getContents();
        $result = json_decode($result, true);
        return $result;
    }
}
