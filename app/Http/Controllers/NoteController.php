<?php

namespace App\Http\Controllers;

use App\Constants\ConfigConstant;
use App\Constants\TimeConstant;
use App\Helpers\TimeHelper;
use App\Http\Requests\NoteOrderDetailStoreRequest;
use App\Http\Requests\NoteOrderRequest;
use App\Http\Resources\Note\OrderNoteResource;
use App\Models\Config;
use App\Models\Customer;
use App\Models\OrderDetail;
use App\Models\OrderDetailNote;
use App\Models\OrderNote;
use App\Models\Staff;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Constants\CustomerConstant;

use App\Http\Controllers\MessageController;
use App\Http\Controllers\ConversationController;
use Illuminate\Support\Facades\App;
use App\Constants\MessageConstant;

class NoteController extends Controller
{
    public function __construct(NoteService $service)
    {
        $this->_service = $service;
    }

    /**
     * @param  string  $id
     * @return JsonResponse
     */
    public function getOrderDetail(string $id): JsonResponse
    {
        return $this->getList(OrderDetailNote::class, $id, 'order_detail_id');
    }

    /**
     * @param  NoteOrderDetailStoreRequest  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function storeOrderDetail(NoteOrderDetailStoreRequest $request, string $id): JsonResponse
    {
        return $this->storeNote(OrderDetailNote::class, $id, 'order_detail_id');
    }
    public function storeOrderDetailArr(string $id): JsonResponse
    {
        return $this->storeNoteArr(OrderDetailNote::class, $id, 'order_detail_id');
    }


    public function editOrderDetail(NoteOrderDetailStoreRequest $request, string $id): JsonResponse
    {
        try {
            $this->_service->editNoteCustomer($id, $request->input('content'), $request->input('priority'));
            return resSuccess('Sửa ghi chú thành công!');
        } catch (\Exception $exception) {
            //dd($exception->getMessage());
            return resError('Sửa ghi chú không thành công!');
        }
    }

    /**
     * @param  string  $id
     * @return JsonResponse
     */
    public function getOrderPublic(string $id, Request $request): JsonResponse
    {
        $supplierId = $request->get('supplier_id');
        return $this->getList(OrderNote::class, $id, 'order_id', true, $supplierId);
    }

    /**
     * @param  string  $id
     * @return JsonResponse
     */
    public function getOrderPrivate(string $id): JsonResponse
    {
        return $this->getList(OrderNote::class, $id, 'order_id', false);
    }

    /**
     * @param  NoteOrderDetailStoreRequest  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function storeOrder(NoteOrderRequest $request, string $id): JsonResponse
    {
        return $this->storeNote(OrderNote::class, $id, 'order_id');
    }

    /**
     * @param  string  $model
     * @param  string  $id
     * @param  string  $column
     * @param  bool|null  $isPublic
     * @return JsonResponse
     */
    private function getList(string $model, string $id, string $column, ?bool $isPublic = null, ?int $supplierId = null): JsonResponse
    {
        $query = (new $model)::query()->where($column, $id);
        if (!is_null($isPublic)) {
            $query->where('is_public', $isPublic);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if (request()->get('by_admin')) {
            $query->where('subject_type', Staff::class);
        }
        if (request()->get('by_customer')) {
            $query->where('subject_type', Customer::class);
        }
        $query->orderBy('created_at', 'desc');

        return resSuccessWithinData(
            $query->get()->map(
                function ($item) use ($column) {
                    return new OrderNoteResource($item, $column);
                }
            )
        );
    }

    /**
     * @param  string  $model
     * @param  string  $id
     * @param  string  $column
     * @param  bool|null  $isPublic
     * @return JsonResponse
     */
    private function storeNote(string $model, string $id, string $column): JsonResponse
    {
        $data = ['id' => $id, 'column' => $column];
        $orderDetail = OrderDetail::with('order.customer')
            ->select('id', 'order_id')
            ->find($id);
        if($model === OrderDetailNote::class) {
            $data['order_id'] = $orderDetail->order_id ?? null;
        }

        // Lấy ra khách hàng thuộc bên nào
        $customerSource = $this->getSourceCustomer($orderDetail);
        $contentNew     = $this->replaceContent(request()->get('content'), $customerSource);

        // Gán lại
        request()->merge([
            'content' => $contentNew,
        ]);
        // Update lại các tin nhắn trước đó k phải cuối cùng
        if(isset($data['order_id']) && request()->get('supplier_id')) {
            $this->_service->updateIsLastest($data, request()->get('supplier_id'));
        }

        // store
        $data['is_lastest'] = 1;
        if(request()->has('priority')) {
            $newNote = $this->_service->storeNoteDetail(
                $model,
                $data + request()->only('content', 'supplier_id', 'is_public', 'priority'),
            );
        } else {
            $newNote = $this->_service->store(
                $model,
                $data + request()->only('content', 'supplier_id', 'is_public'),
            );
        }
        $dataRes = new OrderNoteResource($newNote, $column);
        // save histories
        $this->_service->saveNoteHistory($model, $newNote->id, request()->input('content'));
        OrderDetail::query()->where('id', $id)
            ->update(['note_staff_latest' => request()->input('content') ?? '']);

        // Thêm tạo hội thoại và thêm tin nhắn cho khách
        $contentChat = request()->input('content');
        $data_conversation = $this->makeConversation($id, $contentChat, $orderDetail);
        $dataRes['data_conversation'] = $data_conversation;

        return resSuccessWithinData($dataRes);
    }

    public function makeConversation($order_detail_id, $contentChat, $orderDetailDB)
    {
        $orderDetailModel = \App::make('App\Models\OrderDetail');
        $supplierModel = \App::make('App\Models\Supplier');
        $orderDB = $orderDetailDB->order;

        if (is_null($orderDB)) {
            return;
        }

        // Xử lý hội thoại
        $conversationController = App::make(ConversationController::class);
        $customerModel = \App::make('App\Models\Customer');
        $userOnline = $customerModel->where('id', $orderDB->customer_id)->first();
        $params = [
            'type'        => 1,
            'customer_id' => $orderDB->customer_id,
            'order_id'    => $orderDB->id
        ];
        $result_check = $conversationController->handleBeforeStoreV2($params, $userOnline);
        if(!is_array($result_check)) {
            return;
        }
        $conversationDB = $conversationController->handlerStoreV2($userOnline, $result_check);
        unset($conversationDB->new);

        // Xử lý tin nhắn
        $mesController = App::make(MessageController::class);

        $orderDetailDBs = $orderDetailModel->where('order_id', $orderDetailDB->order_id)->get();
        $collection = collect($orderDetailDBs->toArray());
        $grouped = $collection->groupBy('supplier_id');
        $grouped->all();
        $reversed = array_reverse($grouped->toArray(), true);

        $index_product = 0;
        $index_Sup     = 0;
        $name_supplier = "";
        foreach ($reversed as $key_reversed => $item) {
            foreach ($item as $key_item => $item_child_1) {
                if ($item_child_1['id'] == $order_detail_id) {
                    $index_product = $key_item + 1;
                    $i = array_search($key_reversed, array_keys($reversed));
                    $index_Sup = $i + 1;
                    $supplierDB = $supplierModel->where('id', $key_reversed)->first();
                    if (!is_null($supplierDB)) {
                        $name_supplier = $supplierDB->name;
                    }
                }
            }
        }

        // Thêm về sản phẩm
        $contentItem = [
            'index_product'  => $index_product,
            'index_Sup'      => $index_Sup,
            'image'          => $orderDetailDB->getImage(),
            'name'           => $orderDetailDB->name,
            'name_supplier'  => $name_supplier,
            'quantity'       => $orderDetailDB->quantity,
            'classification' => $orderDetailDB->classification,
        ];
        $params1 = [
            'conversation_id' => $conversationDB->id,
            'type'            => MessageConstant::TYPE_ITEM,
            'content'         => json_encode($contentItem, JSON_UNESCAPED_UNICODE)
        ];
        $messageDB1 = $mesController->handlerStoreMessage($conversationDB, $userOnline, $params1);

        // Thêm tin nhắn sản phẩm
        $params2 = [
            'conversation_id' => $conversationDB->id,
            'type'            => MessageConstant::TYPE_TEXT,
            'content'         => $contentChat
        ];
        $messageDB2 = $mesController->handlerStoreMessage($conversationDB, $userOnline, $params2);

        // Set hội thoại về hoàn thành luôn để admin k cần nhìn thấy xử lý
        $conversationDB->conversation_config_id = 3; // Hoàn thành
        $conversationDB->save();

        return [
            'messageDB1'      => $messageDB1,
            'messageDB2'      => $messageDB2,
            'customer_code'   => $userOnline->code,
            'conversation_id' => (int)$conversationDB->id
        ];
    }


    private function storeNoteArr(string $model, string $id, string $column): JsonResponse
    {
        $data = ['id' => $id, 'column' => $column];
        $orderDetail = OrderDetail::with('order.customer')
            ->select('id', 'order_id')
            ->find($id);
        if($model === OrderDetailNote::class) {
            $data['order_id'] = $orderDetail->order_id ?? null;
        }
        $arr_note_select = request()->get('arr_note_select');
        $content_note_staff_latest = '';
        $newLine = "\r\n\r\n";

        // Lấy ra khách hàng thuộc bên nào
        $customerSource = $this->getSourceCustomer($orderDetail);

        // Update lại các tin nhắn trước đó k phải cuối cùng
        if(isset($data['order_id']) && request()->get('supplier_id')) {
            $this->_service->updateIsLastest($data, request()->get('supplier_id'));
        }
        foreach($arr_note_select as $item){
            // Code 22-04-25
            $item['content'] = $this->replaceContent($item['content'], $customerSource);

            $content_note_staff_latest .= $item['content'] . $newLine;
            // store
            $data['content'] = $item['content'];
            $data['priority'] = !empty($item['priority']) ? $item['priority'] : 0;
            $data['supplier_id'] = \request()->get('supplier_id');

            // store
            $data['is_lastest'] = 1;
            if (!empty($item['priority'])) {
                $newNote = $this->_service->storeNoteDetail($model, $data);
            } else {
                unset($data['priority']); // Xóa priority nếu không cần thiết
                $newNote = $this->_service->store($model, $data);
            }
            $dataRes = new OrderNoteResource($newNote, $column);
            $this->_service->saveNoteHistory($model, $newNote->id, $item['content']);
        }
        if(empty($orderDetail)) {
            return resError('Order Detail not found');
        }
        OrderDetail::query()
            ->where('id', $orderDetail->id)
            ->update(['note_staff_latest' => $content_note_staff_latest ?? '']);

        // Thêm tạo hội thoại và thêm tin nhắn cho khách
        $data_conversation = $this->makeConversation($id, $content_note_staff_latest, $orderDetail);
        $dataRes['data_conversation'] = $data_conversation;

        return resSuccessWithinData($dataRes);

    }

    public function getSourceCustomer($orderDetail)
    {
        if (!$orderDetail || !$orderDetail->order || !$orderDetail->order->customer) {
            return CustomerConstant::SOURCE_HV;
        }

        return $orderDetail->order->customer->source ?? CustomerConstant::SOURCE_HV;
    }

    public function replaceContent($content, $customerSource)
    {
        $arr_congty = Customer::LIST_SOURCE;
        $arr_url = Customer::LIST_URL;
        $content = str_replace("@congty", $arr_congty[$customerSource], $content);

        return str_replace("@url", $arr_url[$customerSource], $content);
    }

    public function deleteOrderDetail(int $id): JsonResponse
    {
        try {
            $this->_service->deleteNoteOrderDetail($id);
            return resSuccess('Xóa ghi chú thành công!');
        } catch (\Exception $exception) {
            //dd($exception->getMessage());
            return resError('Xóa ghi chú không thành công!');
        }
    }

    public function noteWarningOrder()
    {
        return Config::query()->where('key', ConfigConstant::NOTE_WARNING_ORDER)->first()->value;
    }

    public function updateNoteWarningOrder()
    {
        return Config::query()
            ->where('key', ConfigConstant::NOTE_WARNING_ORDER)
            ->update(['value' => request()->input('value')]);
    }

    public function supplierNotesPopup(Order $order)
    {
        $supplierList = DB::table('order_details')
            ->select('supplier_id')
            ->where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->orderBy('supplier_id', 'DESC')
            ->pluck('supplier_id')
            ->values();
        $supplierIndexMap = [];
        foreach ($supplierList as $index => $supplierId) {
            $supplierIndexMap[$supplierId] = $index + 1;
        }

        $firstOrderDetailPerSupplier = DB::table('order_details')
            ->select([
                'supplier_id',
                DB::raw('MIN(id) as first_order_detail_id'),
            ])
            ->where('order_id', $order->id)
            ->whereNull('deleted_at')
            ->groupBy('supplier_id');

        $latestNotes = DB::table('order_detail_notes')
            ->select([
                'order_detail_id',
                DB::raw('MAX(created_at) as latest_created_at'),
            ])
            ->groupBy('order_detail_id');

        $data = OrderDetailNote::query()
            ->joinSub($firstOrderDetailPerSupplier, 'fod', function ($join) {
                $join->on(
                    'order_detail_notes.order_detail_id',
                    '=',
                    'fod.first_order_detail_id'
                );
            })
            ->joinSub($latestNotes, 'ln', function ($join) {
                $join->on('order_detail_notes.order_detail_id', '=', 'ln.order_detail_id')
                    ->on('order_detail_notes.created_at', '=', 'ln.latest_created_at');
            })
            ->with('supplier:id,name')
            ->get()
            ->map(function ($item) use ($supplierIndexMap) {
                return [
                    'stt'           => $supplierIndexMap[$item->supplier_id] ?? null,
                    'supplier_name' => optional($item->supplier)->name,
                    'content'       => $item->content,
                    'created_at' => TimeHelper::format($item->created_at, TimeConstant::DATETIME_BY_DAY_HI),
                ];
            })
            ->sortBy('stt')
            ->values();

        return resSuccessWithinData($data);
    }

}
