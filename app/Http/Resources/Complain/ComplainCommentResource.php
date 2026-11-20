<?php

namespace App\Http\Resources\Complain;

use App\Helpers\MediaHelper;
use App\Constants\TimeConstant;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplainCommentResource extends JsonResource
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
            'id' => $this->id,
            'content' => $this->content,
            'attachments' => (new ListResource($this->attachments, AttachmentResource::class)),
            'sender' => $this->getSender(),
            'prefix' => optional(optional($this->cause)->department)->name ?? 'Nhân Viên',
            'time' => $this->time,
            'created_at' => $this->created_at->format(TimeConstant::HOUR_MINUTE)
        ];
    }

    private function getSender()
    {
        $sender = optional($this->cause)->only('id', 'name', 'avatar');
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
