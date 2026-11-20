<?php

namespace App\Http\Resources\User;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UserRealResource extends JsonResource
{
     /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $roles = $this->roles;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'code' => $this->code,
            'phone_number' => $this->phone_number,
            'department_id' => $this->department_id,
            'status' => $this->status,
            'organization_id' => $this->organization_id,
            'roles' => $roles,
            'id_taobao' => $this->id_taobao,
            'user_name_taobao' => $this->user_name_taobao,
            'password_taobao' => $this->password_taobao,
            'id_1688' => $this->id_1688,
            'organization' => $this->organization,
            'group_id' => $this->current_group_id,
        ];
    }
}
