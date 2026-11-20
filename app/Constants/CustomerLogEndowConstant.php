<?php


namespace App\Constants;


class CustomerLogEndowConstant
{

    public const TYPES = [
        "offer_rate" => "% Đặt hàng",
        "deposit_rate" => "% Đặt cọc đơn Order",
        "consignment_rate" => "% Đặt cọc đơn Ký gửi",
        "level_rate" => "Cấp độ",
        "trans_weight_hn_fee" => "Phí vận chuyển HN (/kg)",
        "trans_weight_hcm_fee" => "Phí vận chuyển HCM (/kg)",
        "trans_weight_hp_fee" => "Phí vận chuyển HP (/kg)",
        "trans_volumn_hn_fee" => "Phí vận chuyển HN (/m3)",
        "trans_volumn_hcm_fee" => "Phí vận chuyển HCM (/m3)",
        "trans_volumn_hp_fee" => "Phí vận chuyển HP (/m3)",

        "cn_trans_weight_hn_fee" => "CN Phí vận chuyển HN (/kg)",
        "cn_trans_weight_hcm_fee" => "CN Phí vận chuyển HCM (/kg)",
        "cn_trans_weight_hp_fee" => "CN Phí vận chuyển HP (/kg)",
        "cn_trans_volumn_hn_fee" => "CN Phí vận chuyển HN (/m3)",
        "cn_trans_volumn_hcm_fee" => "CN Phí vận chuyển HCM (/m3)",
        "cn_trans_volumn_hp_fee" => "CN Phí vận chuyển HP (/m3)",

        "debt" => "Khách hàng được phép công nợ",
    ];
}
