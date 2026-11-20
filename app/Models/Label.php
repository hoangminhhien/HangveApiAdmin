<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Label extends BaseModel
{
    use HasFactory, Uuid;

    public CONST CONSIGNMENT_ORDERED = 'KI_GUI_DA_LEN_DON';
    public CONST ORDERED = 'DA_LEN_DON';
    public CONST KI_GUI = 'KI_GUI';
    public CONST DA_DAT_HANG = 'DA_DAT_HANG';

    public const THAM_KHAO = 'THAM_KHAO';
    public const KHONG_TUONG_TAC_DUOC = 'KHONG_TUONG_TAC_DUOC';
    public const KHONG_CO_NHU_CAU = 'KHONG_CO_NHU_CAU';
    public const MAT_HANG_KHONG_NHAP_DUOC = 'MAT_HANG_KHONG_NHAP_DUOC';
//    public const HEN_GOI_LAI = 'HEN_GOI_LAI';
    public const DANG_FOLLOW = 'DANG_FOLLOW';
//    public const THAM_KHAO_CHUA_CO_NHU_CAU_NGAY = 'THAM_KHAO_CHUA_CO_NHU_CAU_NGAY';
    public const CHUYEN_TIEN_QUOC_TE = 'CHUYEN_TIEN_QUOC_TE';
}
