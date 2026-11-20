<?php
namespace App\Http\Resources\Chat;

use App\Constants\DeliveryConstant;
use App\Constants\TimeConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Services\DeliveryService;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

use App\Helpers\MediaHelper;

use App\Constants\ConversationConstant;
use App\Constants\ComplainConstant;
use App\Services\OrderPackageService;

class ChatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                => (int) $this->id,

            'order_id'          => $this->order_id,
            'order_code'        => $this->order_code,

            'user_id'           => $this->user_id,
            'user_code'         => $this->user_code,
            'user_name'         => $this->user_name,
            'user_email'        => $this->user_email,
            'user_image'        => MediaHelper::getFullUrlByValue($this->user_image),

            'content_last_send' => $this->content_last_send,

            'time_last_send'   => $this->time_last_send ? $this->time_last_send->format('Y-m-d H:i:s') : null,

            'created_at'         => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null
        ];
    }

}
