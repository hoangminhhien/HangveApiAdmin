<?php

namespace App\Http\Controllers;

use App\Constants\FineConstant;
use App\Helpers\ValidationHelper;
use App\Http\Requests\Fine\FineChangeStatusRequest;
use App\Http\Requests\FineCommentStoreRequest;
use App\Http\Resources\FineCommentResource;
use App\Http\Resources\ListResource;
use App\Interfaces\Validation\StoreValidationInterface;
use App\Interfaces\Validation\UpdateValidationInterface;
use App\Models\Consignment;
use App\Models\Fine;
use App\Models\FineComment;
use App\Models\Order;
use App\Services\FineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

use App\Exports\FineExport;


class FineController extends Controller implements StoreValidationInterface, UpdateValidationInterface
{
    public function __construct(FineService $service)
    {
        $this->_service = $service;
    }

    //
    public function storeMessage(): ?array
    {
        return [];
    }

    public function storeRequest(): array
    {
        return [
            "type" => "required|in:".implode(',', array_keys(FineConstant::TYPES)),
            "user_id" => "required|exists:users,id",
            "department_id" => "required|exists:departments,id",
            "reason" => "max:500",
            "solution" => "max:500",
            "order_id" => 'nullable|exists:orders,id',
            "order_code" => ['nullable',
                function ($attribute, $value, $fail) {
                    if (!Order::query()->where('code', '=', $value)->exists() && !Consignment::query()->where(
                            'code',
                            '=',
                            $value
                        )->exists()) {
                        $fail(trans('fine.code_not_exists', ['code' => $value]));
                    }
                },
            ],
            "bill_code" => "nullable|exists:order_package,bill_code",
            "amount" => "required|numeric|min:1",
            "images" => 'required|array'
        ];
    }

    /**
     * @return string[]
     */
    protected function getAttributes(): array
    {
        return [
            'type' => 'Loại nộp phạt',
            'user_id' => 'Nhân viên',
            'reason' => 'Nguyên nhân',
            'solution' => 'Giải pháp',
            'order_code' => 'Mã đơn hàng',
            'bill_code' => 'Mã vận đơn',
            'amount' => 'Số tiền',
            'order_id' => 'Mã đơn hàng',
            'images' => 'Ảnh nộp phạt'
        ];
    }

    public function updateMessage(): array
    {
        return [];
    }

    public function updateRequest(string $id): array
    {
        $data = $this->storeRequest();
        ValidationHelper::prepareUpdateAction($data, $id);
        return $data;
    }

    /**
     * @param  string  $id
     * @return JsonResponse
     */
    public function getComments(string $id): JsonResponse
    {
        return resSuccessWithinData(
            new ListResource(FineComment::query()->where('fine_id', $id)->get(), FineCommentResource::class)
        );
    }

    /**
     * @param  FineCommentStoreRequest  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function addComment(FineCommentStoreRequest $request, string $id): JsonResponse
    {
        $params = $request->all();
        $params['user_id'] = Auth::user()->id;
        $params['fine_id'] = $id;
        Fine::query()->find($id)->increment('comment_number');
        // Thêm noti
        $this->updateFineNotiAddComment($id, $params['content']);
        return resSuccessWithinData(new FineCommentResource(FineComment::query()->create($params)));
    }

    public function updateFineNotiAddComment($fine_id, $content)
    {
        $fineModel = \App::make('App\Models\Fine');
        $fine = $fineModel->where('id', $fine_id)->first();

        $notificationService = \App::make('App\Services\NotificationService');

        $data_fine = [
            'fine_id'    => $fine->id,
            'content'    => $content
        ];

        $data = [
            'group'           => 6,
            'name'            => 6002, // Ý kiến từ user khác
            'data'            => json_encode($data_fine),
            'time_create'     => time(),
            'status'          => 1
        ];

        $notificationService->storeNoti($fine->user_id, $data);
    }

    /**
     * @param  Fine  $fine
     * @return JsonResponse
     */
    public function cancel(Fine $fine): JsonResponse
    {
        $fine->status = FineConstant::KEY_STATUS_CANCEL;
        $fine->save();
        return resSuccess();
    }

    /**
     * @param  FineChangeStatusRequest  $request
     * @param  Fine  $fine
     * @return JsonResponse
     */
    public function changeStatus(FineChangeStatusRequest $request, Fine $fine): JsonResponse
    {
        $fine->status = $request->status;
        $fine->save();
        // Thêm noti
        $this->updateFineNotiStatus($fine);
        return resSuccess();
    }

    public function updateFineNotiStatus($fine)
    {
        $notificationService = \App::make('App\Services\NotificationService');

        $data_fine = [
            'fine_id'    => $fine->id,
            'status'     => $fine->status,
            'status_txt' => \App\Constants\FineConstant::STATUSES[$fine->status],
        ];

        $data = [
            'group'           => 6,
            'name'            => 6003, // Update trạng thái
            'data'            => json_encode($data_fine),
            'time_create'     => time(),
            'status'          => 1
        ];

        $notificationService->storeNoti($fine->user_id, $data);
    }

    public function exportFine(Request $request)
    {
        try {
            return Excel::download(
                new FineExport($request->all()),
                'fine_' . now()->format('Ymd_His') . '.xlsx'
            );
        } catch (\Exception $e) {
            \Log::error($e);
            return resError();
        }
    }
}
