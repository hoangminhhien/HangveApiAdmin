<?php
namespace App\Http\Resources\ConversationConfig;

use App\Constants\DeliveryConstant;
use App\Constants\TimeConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Services\DeliveryService;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

use App\Helpers\MediaHelper;

class ConversationConfigResource extends JsonResource
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
            'id'        => (int) $this->id,
            'name'      => $this->name,
            'color'     => $this->color,
            'time'      => $this->time,
            'create_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i') : null
        ];
    }
}
