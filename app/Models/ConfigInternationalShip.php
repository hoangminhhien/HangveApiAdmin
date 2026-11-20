<?php

namespace App\Models;
use App\Scopes\Traits\Filterable;

class ConfigInternationalShip extends BaseModel
{
    use Filterable;

    public $fillable = [
        'organization_id',
        'type',
        'date_start',
        'matrix_price',
        'list_province',
        'status',
        'official'
    ];

    protected $table = 'config_international_ships';
    public $timestamps = true;
    protected $dates = ['date_start'];
    const OFFICIAL_1 = 1;
    const OFFICIAL_2 = 2;
    const LIST_OFFICAL = [
        self::OFFICIAL_1 => 'Bán chính ngạch',
        self::OFFICIAL_2 => 'Chính ngạch'
    ];

    public function scopeName($query)
    {
        return $query->where('name', request()->query('name'));
    }

}
