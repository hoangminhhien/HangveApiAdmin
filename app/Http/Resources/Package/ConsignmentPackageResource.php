<?php

namespace App\Http\Resources\Package;

use App\Helpers\OrderHelper;
use App\Helpers\StatusHelper;
use App\Helpers\TimeHelper;
use App\Models\OrderPackage;
use App\Services\OrderPackageService;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsignmentPackageResource extends JsonResource
{
    public function toArray($request): array
    {
        $total_fee = [];
        if ($this->official == OrderPackage::OFFICIAL_BCN) {
            $total_fee = $this->totalBCN();
        }
        $is_percent_cost = false;
        if ($this->official == OrderPackage::OFFICIAL_CN) {
            if ($this->type_official == OrderPackage::TYPE_OFFICIAL_TD) {
                $total_fee = $this->totalCNTD();
                $is_percent_cost = true;
            }
            if ($this->type_official == OrderPackage::TYPE_OFFICIAL_UT) {
                $total_fee = $this->totalCNUT();
            }
        }
        $order_cost = (float)$this->order_cost ?? 0;
        $type_official = $this->type_official ?? 0;
        $official = $this->official ?? 1;
        $percent_cost = 0;
        $percent_value = 0;
        if ($official == OrderPackage::OFFICIAL_CN && $type_official == OrderPackage::TYPE_OFFICIAL_TD) {
            $calculator = OrderHelper::calculatorConsignmentRate($order_cost);
            $percent_cost = $calculator['percent_cost'] ?? 0;
            $percent_value = $calculator['percent_value'] ?? 0;
        }
        return [
            'id' => $this->id ?? 0,
            'consignment_detail_id' => $this->consignmentDetail->id ?? 0,
            'consignment_id' => $this->consignmentDetail->consignment_id ?? 0,
            'bill_code' => $this->bill_code ?? null,
            'color' => $this->official == OrderPackage::OFFICIAL_BCN
                ? '#2196F3'
                : '#FFBD14',
            'official' => $this->official ?? 1,
            'type_official' => $type_official,
            'official_name' => $this->official == OrderPackage::OFFICIAL_BCN
                ? 'Bán chính ngạch'
                : 'Chính ngạch',
            'created_at' => TimeHelper::format($this->created_at),
            'status' => (new OrderPackageService())->getStatus($this->status) + StatusHelper::getTime(
                    $this->id,
                    OrderPackage::class,
                    $this->status
                ),
            'type_of_goods' => 'Ký gửi',
            'weight_origin' => $this->weight_real ?? 0,
            'weight_round' => $this->weight ?? 0,
            'transporter' => optional($this->transporterRelation)->name ?? $this->transporter,
            'amount' => $this->amount ?? 0,
            'category' => optional($this->categoryRelation)->name ?? $this->category,
            'type_quantitative' => $this->type_quantitative ?? 0,
            'volume' => $this->volume ?? 0,
            'order_cost' => $order_cost,
            'percent_cost' => $percent_cost,
            'percent_value' => $percent_value,
            'is_percent_cost' => $is_percent_cost,
            'exchange_rate' => (float) $this->exchange_rate ?? 0,
            'insurance_cost' => (float) $this->insurance_cost ?? 0, // phi bao hiem
            'woodworking_cost' => (float) $this->woodworking_cost ?? 0, // phi dong go
            'shock_proof_cost' => (float) $this->shock_proof_cost ?? 0, // phi chong shock
            'international_shipping_cost' => (float) $this->international_shipping_cost ?? 0, // phi vc quoc te
            'delivery_cost' => (float) $this->delivery_cost ?? 0, // phi ship noi dia
            'official_service_fee' => (float) $this->official_service_fee ?? 0, // phi dich vu chinh ngach lay tu Bao Gia,
            'special_consumption_tax' => (float) $this->special_consumption_tax ?? 0, // Thue suat tieu thu dac biet lay tu Bao Gia
            'tax_xnk' => (float) $this->tax_xnk ?? 0, // Thue suat XNK lay tu Bao Gia
            'tax_vat' => (float) $this->tax_vat ?? 0, // Thue suat VAT lay tu Bao Gia
            'environmental_tax' => (float) $this->environmental_tax ?? 0, // Thue suat moi truong lay tu Bao Gia
            'safeguard_tax' => (float) $this->safeguard_tax ?? 0, // Thue suat tu ve lay tu Bao Gia
            'surcharge' => (float) $this->surcharge ?? 0, // Phu phi lay tu Bao Gia
            'total_fee' => $total_fee,
            'is_shock_proof' => $this->is_shock_proof ?? 0,
            'is_woodworking' => $this->is_woodworking ?? 0,
            'is_insurance' => $this->is_insurance ?? 0,
            "description" => $this->description ?? null,
            "note" => $this->note ?? null,
            "delivery_type" => $this->delivery_type ?? null,
            "quantity" => $this->quantity ?? 0,
            'transporter_id' => $this->transporter_id ?? 0,
            'category_id' => $this->category_id ?? 0,
            'product_name' => $this->product_name ?? null,
            'package_number' => $this->package_number ?? 0,
            'in_transit' => $this->in_transit ?? 'cn-vi',
            'in_transit_name' => 'Trung quốc - Việt Nam',
            'image_path' => $this->consignmentDetail->image ?? null,
            'loading' => false
        ];
    }
    /* Bán chính ngạch */
    private function totalBCN(): array
    {
        $insurance_cost = $this->insurance_cost ?? 0; // phi bao hiem
        $woodworking_cost = $this->woodworking_cost ?? 0; // phi dong go
        $shock_proof_cost = $this->shock_proof_cost ?? 0; // phi chong shock
        $international_shipping_cost = $this->international_shipping_cost ?? 0; // phi vc quoc te
        $delivery_cost = $this->delivery_cost ?? 0; // phi ship noi dia
        $total = $insurance_cost + $woodworking_cost + $shock_proof_cost + $international_shipping_cost + $delivery_cost;
        $paid = 0;
        $remaining = $total - $paid;
        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
        ];
    }
    /* Chính ngạch tự đứng tên tờ khai */
    private function totalCNTD(): array
    {
        $official_service_fee = $this->official_service_fee ?? 0; // phi dich vu chinh ngach lay tu Bao Gia,
        $insurance_cost = $this->insurance_cost ?? 0; // phi bao hiem
        $woodworking_cost = $this->woodworking_cost ?? 0; // phi dong go
        $shock_proof_cost = $this->shock_proof_cost ?? 0; // phi chong shock
        $international_shipping_cost = $this->international_shipping_cost ?? 0; // phi vc quoc te
        $delivery_cost = $this->delivery_cost ?? 0; // phi ship noi dia
        $total = $insurance_cost + $woodworking_cost
            + $shock_proof_cost + $international_shipping_cost
            + $delivery_cost + $official_service_fee;
        $paid = 0;
        $remaining = $total - $paid;
        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
        ];
    }

    /* Chính ngạch Ủy thác nhập khẩu  */
    private function totalCNUT(): array
    {
        $official_service_fee = $this->official_service_fee ?? 0; // phi dich vu chinh ngach lay tu Bao Gia,
        $insurance_cost = $this->insurance_cost ?? 0; // phi bao hiem
        $woodworking_cost = $this->woodworking_cost ?? 0; // phi dong go
        $shock_proof_cost = $this->shock_proof_cost ?? 0; // phi chong shock
        $international_shipping_cost = $this->international_shipping_cost ?? 0; // phi vc quoc te
        $delivery_cost = $this->delivery_cost ?? 0; // phi ship noi dia
        $special_consumption_tax = $this->special_consumption_tax ?? 0; // Thue suat tieu thu dac biet lay tu Bao Gia
        $tax_xnk = $this->tax_xnk ?? 0; // Thue suat XNK lay tu Bao Gia
        $tax_vat = $this->tax_vat ?? 0; // Thue suat VAT lay tu Bao Gia
        $environmental_tax = $this->environmental_tax ?? 0; // Thue suat moi truong lay tu Bao Gia
        $safeguard_tax = $this->safeguard_tax ?? 0; // Thue suat tu ve lay tu Bao Gia
        $surcharge = $this->surcharge ?? 0; // Phu phi lay tu Bao Gia
        $total = $insurance_cost + $woodworking_cost
            + $shock_proof_cost + $international_shipping_cost
            + $delivery_cost + $official_service_fee
            + $special_consumption_tax + $tax_xnk + $tax_vat
            + $environmental_tax + $safeguard_tax + $surcharge
        ;
        $paid = 0;
        $remaining = $total - $paid;
        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
        ];
    }
}
