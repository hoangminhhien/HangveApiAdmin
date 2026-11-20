<?php

namespace App\Http\Resources\FilePrint;

use App\Constants\FilePrintConstant;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class FilePrintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $dataJson = json_decode($this->data_json);

        $userName = data_get($this->user, 'name');

        if (
            $this->user_dongdong_id
        ) {

            $userName = $this->user_dongdong_name ;
        }

        return [
            'id' => $this->id,
            'user_name' => $userName,
            'type_text' => $this->type == FilePrintConstant::KEY_TYPE_PACK_RECEIVE_GOODS
                ? 'Tại kho'
                : 'Giao hàng',
            'customer_name' => data_get($this->customer, 'name'),
            'customer_phone' => data_get($this->customer, 'phone_number'),
            'pack_quantity' => data_get($dataJson, 'packQuantity'),
            'created_at' => Carbon::parse($this->created_at)->format('d/m/Y H:i:s'),
            'check_full' => $this->getFilePrintCheck($dataJson)
        ];
    }

    public function getFilePrintCheck($dataJson)
    {
        $filePrintCheckModel = \App::make('App\Models\FilePrintCheck');
        $filePrintCheckDBCount = $filePrintCheckModel->where('file_print_id', $this->id)->count();
        if ($filePrintCheckDBCount == $dataJson->packQuantity) {
            return true;
        }
        return false;
    }
}
