<?php


namespace App\Services;


use App\Constants\ConversationConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Chat\ChatResource;
use App\Http\Resources\ListResource;
use App\Models\Chat;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;

class ChatService extends BaseService
{
    protected string $_resource = ChatResource::class;
    protected array $_relations = [];

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $userOnline = \Auth::user();
        $columns = \Schema::getColumnListing('chat_ais');
        $exclude = [
            'items',
            'items_cache',
            'item_ids',
            'summary',
            'attribute_name',
            'options',
        ];
        $selectColumns = array_diff($columns, $exclude);

        $data = $this->_model->newQuery()
                            ->select($selectColumns)
                            ->orderBy('time_last_send', 'desc')
                            ->with($this->_relations);

        // Nếu không có quyền thì chỉ fetch theo chính tài khoản cúa nó thôi
        $viewAll = $userOnline->checkUserHasPer('chat_ai.view-full-list');
        if (!$viewAll) {
            $data = $data->where('user_id', $userOnline->id);
        }

        // $data = $data->where('user_id', $userOnline->id);

        $data = $data->paginate($perPage);
        return resSuccessWithinData(new $this->_paginateResource($data, $this->_resource));
    }

    public function getDetail(array $params = []){
        $query = $this->_model->newQuery();
        if (!empty($params['type']) && in_array($params['type'], [
                ConversationConstant::TYPE_ORDER,
                ConversationConstant::TYPE_PACKAGE,
                ConversationConstant::TYPE_COMPLAIN,
            ])
        ) {
            $query->where('type', $params['type']);
        } else {
            return false;
        }

        if (!empty($params['type_id'])) {
            $query->where('type_id', $params['type_id']);
        } else {
            return false;
        }

        if (!empty($params['customer_id'])) {
            $query->where('customer_id', $params['customer_id']);
        }
        return $query->first();
    }

}
