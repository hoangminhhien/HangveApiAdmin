<?php


namespace App\Services;


use App\Constants\CustomerConstant;
use App\Constants\DeliveryConstant;
use App\Constants\TransactionConstant;
use App\Helpers\ConvertHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\RandomHelper;
use App\Http\Resources\Conversation\ConversationResource;
use App\Http\Resources\ListResource;
use App\Models\CustomerDelivery;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

use App\Constants\PackageConstant;

class MessageService extends BaseService
{
    protected string $_resource = MessageResource::class;
}
