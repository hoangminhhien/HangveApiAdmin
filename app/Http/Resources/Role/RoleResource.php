<?php

namespace App\Http\Resources\Role;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class RoleResource extends JsonResource
{
     /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => (int)$this->id,
            'name' => $this->name,
            // 'guard_name' => $this->guard_name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'type' => $this->type,
            'permissions'=> $this->permissions
        ];
    }
}
