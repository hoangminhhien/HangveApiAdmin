<?php
namespace App\Http\Resources\Message;

use App\Constants\DeliveryConstant;
use App\Constants\TimeConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Services\DeliveryService;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $messageParent = null;
        if ($this->parent_id > 0) {
            $messageParent = $this->messageParent;

            $data_temp = [
                'source'  => $messageParent->source,
                'type'    => $messageParent->type,
                'content' => $messageParent->content,
            ];
            $messageParent = $data_temp;
        }

        return [
            'id'        => (int) $this->id,
            'source'    => $this->source,
            'type'      => $this->type,
            'content'   => $this->content,
            'parent_id' => $this->parent_id,
            'parent'    => $messageParent,
            'time_sent' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null
        ];
    }
}
