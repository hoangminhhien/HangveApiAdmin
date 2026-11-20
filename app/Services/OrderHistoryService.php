<?php

namespace App\Services;

use App\Abstracts\AbstractBaseService;
use App\Constants\OrderNoteConstant;
use App\Http\Resources\Order\OrderHistoryResource;
use App\Models\OrderHistory;

class OrderHistoryService extends AbstractBaseService
{
    protected string $_resource = OrderHistoryResource::class;
    protected function setModel()
    {
        $namespaceModel = 'App\Models\\' . str_replace('Service', '', class_basename($this));
        $this->_model = class_exists($namespaceModel) ? new $namespaceModel() : null;
    }

    public function setParams($request, $query)
    {
        $query->with(['user' => function ($user) {
            $user->select('id', 'name');
        }]);
        if (!empty($request['order_id'])) {
            $query->where('order_id', $request['order_id']);
        }

        if (!empty($request['history_type'])) {
            $query->where('history_type', $request['history_type']);
        } else {
            $query->where('history_type', '!=', OrderHistory::ORDER_HISTORY_NOTE_BUSINESS_TYPE);
        }
        return $query->orderBy('id', 'DESC');
    }
}
