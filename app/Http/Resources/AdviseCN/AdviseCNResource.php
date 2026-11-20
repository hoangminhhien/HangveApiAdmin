<?php
namespace App\Http\Resources\AdviseCN;

use App\Constants\DeliveryConstant;
use App\Constants\TimeConstant;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Models\Customer;
use App\Services\DeliveryService;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\ReportStatusResource;
use App\Constants\OrderConstant;
use App\Constants\AdviseCNConstant;

use App\Helpers\MediaHelper;

use App\Constants\ConversationConstant;
use App\Constants\ComplainConstant;
use App\Services\OrderPackageService;

class AdviseCNResource extends JsonResource
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

            'customer_name'     => $this->customer_name,
            'customer_phone'    => $this->customer_phone,
            'warehouse_id'      => $this->warehouse_id,
            'warehouse_code'    => $this->warehouse_code,
            'item_name'         => $this->item_name,
            'item_color'        => $this->item_color,
            'item_size'         => $this->item_size,
            'item_material'     => $this->item_material,
            'item_info'         => $this->item_info,
            'item_branch'       => $this->item_branch,
            'item_quantity'     => $this->item_quantity,
            'item_kg'           => $this->item_kg,
            'item_m3'           => $this->item_m3,
            'item_unit_declare' => $this->item_unit_declare,
            'hs_code'           => $this->hs_code,
            'info_fee'          =>
                [
                    "feeVCQT"       => $this->feeVCQT,
                    "t_tax_xnk"     => $this->t_tax_xnk,
                    "t_tax_vat"     => $this->t_tax_vat,
                    "t_tax_ttdb"    => $this->t_tax_ttdb,
                    "t_tax_protect" => $this->t_tax_protect,
                    "t_tax_nature"  => $this->t_tax_nature,
                    "taxXNK"        => $this->taxXNK,
                    "taxTTDB"       => $this->taxTTDB,
                    "taxNature"     => $this->taxNature,
                    "taxProtect"    => $this->taxProtect,
                    "taxVAT"        => $this->taxVAT,
                    "feeService"    => $this->feeService,
                    "feeOpen"       => $this->feeOpen,
                    "total_amount"  => $this->total_amount,
                ],

            'status'            => $this->status,
            'source'            => $this->source,
            'source_name'       => Customer::LIST_SOURCE[$this->source] ?? '',
            'status_text'       => AdviseCNConstant::STATUSES_TEXT[$this->status],
            'status_color'      => AdviseCNConstant::STATUSES_COLOR[$this->status],

            'type'            => $this->type,
            'type_text'       => AdviseCNConstant::TYPE_TEXT[$this->type],

            'user_id'           => $this->user_id,
            'user_name'         => $this->user->name ?? null,

            'created_at'        => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null
        ];
    }

}
