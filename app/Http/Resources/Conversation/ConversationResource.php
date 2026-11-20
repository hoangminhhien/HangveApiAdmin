<?php
namespace App\Http\Resources\Conversation;

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

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $conversationConfigDB = $this->conversationconfig;
        $userDB = $this->user;
        $orderDB = $this->order;
        $customerDB = $this->customer;
        return [
            'id'                        => (int) $this->id,
            'type'                      => $this->type,
            'type_text'                 => $this->getTypeText(),
            'type_id'                   => $this->type_id,
            'type_code'                 => $this->type_code,
            'type_status'               => $this->getStatus(),

            'order_id'                  => $this->order_id,
            'order_code'                => !is_null($orderDB) ? $orderDB->code : null,

            'customer_id'               => $this->customer_id,
            'customer_code'             => $this->customer_code,
            'customer_name'             => $this->customer_name,
            'customer_phone'             => $this->customer_phone,
            'customer_image'            => MediaHelper::getFullUrlByValue($this->customer_image),
            'customer_source'            => !is_null($customerDB) ? $customerDB->source : null,

            'supplier_id'               => $this->supplier_id,
            'supplier_name'             => $this->supplier_name,
            'supplier_image'            => $this->supplier_image,

            'user_id'                   => $this->user_id,
            'user_code'                 => $this->user_code,
            'user_name'                 => $this->user_name,
            'user_email'                => $userDB ? $userDB->email : "Nhân viên không tồn tại",
            'user_image'                => MediaHelper::getFullUrlByValue($this->user_image),

            'unread_client'             => $this->unread_client,
            'unread_admin'              => $this->unread_admin,
            'time_last_send'            => $this->time_last_send,
            'content_last_send'         => $this->content_last_send,

            'conversation_config_id'    => $this->conversation_config_id,
            'conversation_config_text'  => $conversationConfigDB ? $conversationConfigDB->name : "",
            'conversation_config_color' => $conversationConfigDB ? $conversationConfigDB->color : "",

            'new'               => $this->new,
            'user_code_old'     => $this->user_code_old,

            'create_at'                 => $this->created_at ? $this->created_at->format('Y-m-d H:i') : null
        ];
    }

    public function getStatus()
    {
        if ($this->type == ConversationConstant::TYPE_ORDER) {
            return new ReportStatusResource(
                $this->type_status,
                OrderConstant::STATUSES,
                OrderConstant::STATUSES_COLOR
            );
        }
        if ($this->type == ConversationConstant::TYPE_PACKAGE) {
            return (new OrderPackageService())->getStatus($this->type_status);
        }
        if ($this->type == ConversationConstant::TYPE_COMPLAIN) {
            return StatusHelper::getInfo($this->type_status, ComplainConstant::class) + ['value' => $this->type_status];
        }
    }
}
