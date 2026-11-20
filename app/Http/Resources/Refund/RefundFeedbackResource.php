<?php

namespace App\Http\Resources\Refund;

use App\Helpers\MediaHelper;
use App\Constants\TimeConstant;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundFeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $datas = $this->resource;
        $dataFinal = [];
        foreach($datas as $value) {
            $dataFinal[] = [
                'content' => $value['content'],
                'sender' => $this->getSender($value),
                'date' => $value['created_at']->format(TimeConstant::DATETIME_BY_DAY_HIS)
            ];
        }
        $data['items'] = $dataFinal;

        return $data;
    }
    private function getSender($data)
    {
        $sender = optional($data->cause)->only('id', 'name', 'avatar');
        $sender['avatar'] = $this->getAvatar($sender['avatar']);
        
        return $sender;
    }

    private function getAvatar($avatar): string
    {
        $avatarFinal = MediaHelper::getFullUrlByValue('/default.png');

        if($avatar && !str_contains($avatar, 'http')) {
            $avatarFinal = MediaHelper::getFullUrlByValue($avatar);
        } else if($avatar && str_contains($avatar, 'http')) {
            $avatarFinal = $avatar;
        }

        return $avatarFinal;
    }
}
