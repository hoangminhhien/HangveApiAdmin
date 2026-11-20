<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerOfferRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_id'          => 'nullable|exists:customers,id',
            'trans_volumn_hcm_fee' => 'nullable|numeric|min:0|max:10000000',
            'trans_volumn_hn_fee'  => 'nullable|numeric|min:0|max:10000000',
            'trans_volumn_hp_fee'  => 'nullable|numeric|min:0|max:10000000',
            'trans_weight_hcm_fee' => 'nullable|numeric|min:0|max:10000000',
            'trans_weight_hn_fee'  => 'nullable|numeric|min:0|max:10000000',
            'trans_weight_hp_fee'  => 'nullable|numeric|min:0|max:10000000',

            'cn_trans_volumn_hcm_fee' => 'nullable|numeric|min:0|max:10000000',
            'cn_trans_volumn_hn_fee'  => 'nullable|numeric|min:0|max:10000000',
            'cn_trans_volumn_hp_fee'  => 'nullable|numeric|min:0|max:10000000',
            'cn_trans_weight_hcm_fee' => 'nullable|numeric|min:0|max:10000000',
            'cn_trans_weight_hn_fee'  => 'nullable|numeric|min:0|max:10000000',
            'cn_trans_weight_hp_fee'  => 'nullable|numeric|min:0|max:10000000',
        ];
    }

    public function messages()
    {
        return [
            'trans_volumn_hcm_fee.max' => 'Phí vận chuyển không nhập quá 10.000.000',
            'trans_volumn_hn_fee.max'  => 'Phí vận chuyển không nhập quá 10.000.000',
            'trans_volumn_hp_fee.max'  => 'Phí vận chuyển không nhập quá 10.000.000',
            'trans_weight_hcm_fee.max' => 'Phí vận chuyển không nhập quá 10.000.000',
            'trans_weight_hn_fee.max'  => 'Phí vận chuyển không nhập quá 10.000.000',
            'trans_weight_hp_fee.max'  => 'Phí vận chuyển không nhập quá 10.000.000',
        ];
    }
}
