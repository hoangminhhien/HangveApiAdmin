<?php

namespace App\Http\Controllers;

use App\Constants\CustomerConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Http\Requests\Transaction\TransactionPaginateRequest;
use App\Http\Requests\Transaction\WithdrawalChangeStatusRequest;
use App\Http\Resources\PaginateJsonResource;
use App\Http\Resources\WithdrawalResource;
use App\Models\CustomerWithdrawal;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->_service = new TransactionService();
    }

    /**
     * @return JsonResponse
     */
    public function getWithdrawal(): JsonResponse
    {
        /*return resSuccessWithinData(
            new PaginateJsonResource(
                CustomerWithdrawal::query()->paginate(PaginateHelper::getPerPage()),
                WithdrawalResource::class
            )
        );*/

        $user = auth()->user();
        $query = CustomerWithdrawal::query();
        if ($user->checkUserHasPer('fund.index-withdraw')) {
        }
        elseif ($user->checkUserHasPer('fund.index-withdraw-managed')) {
            $query->whereHas('customer', function ($q) use ($user) {
                $q->where('staff_group_id', $user->id);
            });
        }
        else {
            return resError('Bạn không có quyền xem danh sách rút tiền', 403);
        }
        $data = new PaginateJsonResource(
            $query->paginate(PaginateHelper::getPerPage()),
            WithdrawalResource::class
        );
        return resSuccessWithinData($data);
    }

    /**
     * @param  WithdrawalChangeStatusRequest  $request
     * @param  CustomerWithdrawal  $withdrawal
     * @return JsonResponse
     */
    public function withdrawalChangeStatus(
        WithdrawalChangeStatusRequest $request,
        CustomerWithdrawal $withdrawal
    ): JsonResponse {
        $withdrawal->status = $status = $request->input('status');
        $withdrawal->save();
        if ($status === CustomerConstant::KEY_WITHDRAWAL_STATUS_CANCEL) {
            $content = trans(
                'transaction.withdrawal_cancel',
                [
                    'name' => 'Nhân viên '.getCurrentUser()->name,
                    'name' => '',
                    'code' => $withdrawal->code,
                    'amount' => ConvertHelper::numericToVND($withdrawal->amount)
                ]
            );
            (new TransactionService())->setTransactionIncrement(
                $withdrawal->amount,
                TransactionConstant::STATUS_REFUND,
                $content,
                $withdrawal->customer_id,
                $withdrawal
            );
            // push notification for client
            $this->updateNotification($withdrawal, $content);
        } else {
            $statusName = \App\Constants\CustomerConstant::WITHDRAWAL_STATUSES[$status];
            $content = 'Yêu cầu rút tiền được chuyển sang trạng thái ' . $statusName;
             // push notification for client
            $this->updateNotification($withdrawal, $content);
        }
        return resSuccess();
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
    /**
     * @param  CustomerWithdrawal  $withdrawal
     * @return JsonResponse
     */
    public function getWithdrawalDetail(CustomerWithdrawal $withdrawal): JsonResponse
    {
        return resSuccessWithinData(new WithdrawalResource($withdrawal));
    }

    /**
     * get list order paginate by customer
     * @param TransactionPaginateRequest $request
     * @return mixed
     */
    public function getTransactionByCustomer(TransactionPaginateRequest $request)
    {
        try {
            $params = $request->only('customer_id', 'page', 'per_page');
            return $this->_service->getTransactionByCustomer($params);
        } catch (\Exception $e) {
            return resError();
        }
    }

    /**
     * @return JsonResponse
     */
    public function getTransactionType(): JsonResponse
    {
        $data = [];
        foreach (TransactionConstant::STATUSES as $key => $value) {
            $data[] = [
                'value' => $value,
                'key' => $key,
            ];
        }
        return resSuccessWithinData($data);
    }

    public function countWithdrawalStatusPending()
    {
        $user = auth()->user();
        $query = $this->_service->getWithdrawalStatusPending();
        if ($user->checkUserHasPer('fund.index-withdraw')) {
        }
        elseif ($user->checkUserHasPer('fund.index-withdraw-managed')) {
            $query->whereHas('customer', function ($q) use ($user) {
                $q->where('staff_group_id', $user->id);
            });
        }
        else {
            return resError('Bạn không có quyền xem dữ liệu này', 403);
        }
        $count = $query->count();
        return resSuccessWithinData($count);
    }
}
