<?php

namespace App\Http\Resources\Note;

use App\Helpers\TimeHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $user = $this->staff;

        if (!is_null($this->dongdong_id)) {
            $userDD = \App\Models\User::query()
                ->where('dongdong_id', $this->dongdong_id)
                ->where('dongdong_id', '>', 0)
                ->first();

            if (!is_null($userDD)) {
                $user = $userDD;
            }
        }

        return [
            'id' => $this->id,
            'content' => $this->content,
            'created_at' => TimeHelper::format($this->created_at),
            'user' => optional($user)->only('id', 'name'),
            'user_name' => optional($user)->name ?? $this->user_name,
        ];
    }
}
