<?php


namespace App\Services;


use App\Constants\ConversationConstant;
use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Conversation\ConversationResource;
use App\Http\Resources\ListResource;
use App\Models\Conversation;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;

class ConversationService extends BaseService
{
    protected string $_resource = ConversationResource::class;
    protected array $_relations = ['conversationconfig', 'user', 'order', 'customer'];

    public function pagination(int $perPage): JsonResponse
    {
        $this->throwModel();
        $userOnline = \Auth::user();
        $data = $this->_model->newQuery()
                            // ->where('user_id', $userOnline->id)
                            ->orderBy('conversation_config_id', 'asc')
                            ->orderBy('time_last_send', 'desc')->with($this->_relations);

        // Nếu không có quyền thì chỉ fetch theo chính tài khoản cúa nó thôi
        $viewAll = $userOnline->checkUserHasPer('live_chat.view-full-list');
        if (!$viewAll) {
            $data = $data->where('user_id', $userOnline->id);
        }

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
